# ANALYSIS.md - Google Reviews Slider v2.7.11

Fecha de análisis: 2026-07-15. Rama: `main` (commit `8f878cb` + cambios sin commitear que retiran la SerpAPI key hardcodeada).

## 1. Corrección de contexto: el plugin ya no usa Outscraper

El brief dice "jala reseñas vía la API de Outscraper". Eso fue cierto hasta la v2.x temprana. Hoy el plugin extrae reseñas con **SerpAPI** (`GRS_SerpAPI`, [serpapi-handler.php](includes/serpapi-handler.php)). Quedan tres rastros de Outscraper:

- [outscraper-api.php](includes/outscraper-api.php): clase `GRS_Outscraper_API` completa, **nunca incluida** por el plugin. Código muerto.
- [serpapi-handler.php:397](includes/serpapi-handler.php#L397): `class_alias('GRS_SerpAPI', 'GRS_Outscraper_API')`. Si alguien incluye ambos archivos, PHP muere con fatal error por clase duplicada. Es una mina enterrada.
- `readme.txt` y `test-outscraper-mapping.php` siguen hablando de Outscraper.

Todo el análisis que sigue trata a SerpAPI como la fuente real. Los requerimientos R1/R2 aplican igual: los créditos que hay que cuidar son los de SerpAPI.

**Seguridad de llaves:** la SerpAPI key vivió hardcodeada en el código y está en el historial de git público (igual que un token viejo de Outscraper). El working tree ya la retira del código, pero el historial la conserva. **Rotar ambas llaves es obligatorio**; ningún cambio de código lo sustituye.

## 2. Mapa del repo

```
google-reviews-slider.php   Bootstrap: hooks de activación, cron, AJAX de caché/updates
includes/
  serpapi-handler.php       GRS_SerpAPI: fetch, paginación, mapeo, 3 endpoints AJAX
  database-handler.php      GRS_Database: tablas, upsert, queries, historial
  shortcode.php             [google_reviews_slider]: render frontend + fallback legacy
  api-handler.php           PIPELINE LEGACY Google Places (transients) - aún vivo
  admin-page.php            Settings API, mapa de Google, página de opciones
  reviews-manager.php       UI de gestión en admin + 3 endpoints AJAX
  outscraper-api.php        MUERTO: no se incluye en ningún lado
  plugin-updater.php        Auto-update desde GitHub Releases
js/swiper-init.js           Init de Swiper 11 (CDN) + read-more
js/script.js                MUERTO: init de Slick, ya no se encola
css/grs-direct.css          Estilos del slider (activo)
css/style.css               MUERTO: estilos de la era Slick
assets/slick/               MUERTO: librería Slick completa
uninstall.php               Limpieza (con bug: dropea tabla con nombre equivocado)
build/, releases/           Copias de build comiteadas al repo
check-updates.php, force-update-check.php, cleanup-old-folders.php,
test-*.php                  Herramientas sueltas de debug (excluidas del build)
```

`build-exclude.txt` sí excluye los archivos de debug del zip final. El riesgo queda en quien copie el repo completo al servidor.

## 3. Inventario WordPress

### Hooks y cron

| Tipo | Nombre | Handler | Nota |
|---|---|---|---|
| activation | - | `grs_activation_hook` | defaults, tablas, agenda cron |
| deactivation | - | `grs_deactivation_hook` | borra transients y cron |
| filter | `cron_schedules` | `grs_add_cron_schedules` | agrega `monthly` = 30 días |
| cron | `grs_auto_refresh_reviews` | `grs_cron_refresh_reviews` | **borra todo y luego fetchea** |
| shortcode | `[google_reviews_slider]` | `grs_direct_display` | único punto de render frontend |
| action | `wp_enqueue_scripts` | `grs_enqueue_assets` | **global**: dashicons + nonce en cada página del sitio |
| action | `wp_enqueue_scripts` | `grs_direct_enqueue_assets` | condicional por shortcode |
| action | `admin_notices` | `grs_update_notice` | además borra transients como efecto colateral |

### Endpoints AJAX (todos `wp_ajax_`, solo usuarios logueados)

| Acción | Capability | Nonce | Riesgo |
|---|---|---|---|
| `grs_extract_reviews` | manage_options | sí | es el "sync manual"; sin rate limit |
| `grs_delete_all_reviews` | manage_options | sí | destructivo |
| `grs_remove_duplicates` | manage_options | sí | - |
| `grs_get_reviews_table` | manage_options | sí | - |
| `grs_search_place` | manage_options | sí | gasta 1 crédito SerpAPI por búsqueda |
| `grs_check_api_usage` | manage_options | sí | - |
| `grs_clear_cache` | manage_options | sí | - |
| `grs_check_for_updates` | update_plugins | sí | - |
| `grs_test_api` | manage_options | **no** | CSRF posible; gasta crédito y expone email de cuenta |
| `grs_test_ajax` | **ninguna** | **no** | inofensivo pero no debería existir |

### Estado persistido

**wp_options** (autoload por default en todas):

- `grs_settings`: `grs_api_key` (Google Places), `grs_place_id`, `grs_min_rating`, `grs_serpapi_key`, `grs_data_id`, y escritos en runtime por el sync: `grs_business_name`, `grs_business_rating`, `grs_total_reviews`. Config y datos de negocio mezclados en la misma opción; el sync hace `update_option` completo y puede pisar un guardado de settings concurrente ([serpapi-handler.php:204-214](includes/serpapi-handler.php#L204-L214)).
- `grs_version`, `grs_db_version`.

**Transients:**

| Transient | TTL | Escrito por | Leído por |
|---|---|---|---|
| `grs_reviews` | 30 días | pipeline legacy Google Places | fallback del frontend |
| `grs_total_review_count` | 30 días | pipeline legacy | nadie lo lee ya (solo se borra) |
| `grs_google_total_reviews` | 24 h | shortcode (llamada en pleno render) | shortcode |
| `grs_fetch_lock_{md5}` | 15 min | shortcode (circuit breaker v2.7.11) | shortcode |
| `grs_github_release_*` | - | updater | updater |

**Tablas custom:**

- `wp_grs_reviews`: PK `id`, UNIQUE (`place_id`,`review_id`), `rating int(1)`, `text text`, `time int(11)` (unix), `relative_time_description varchar` (string congelado), `extracted_at datetime`, `source varchar`.
- `wp_grs_extraction_history`: log de extracciones (fecha, status, count, error).

## 4. Flujo de datos completo

### Pipeline A - SerpAPI (el actual)

```
Admin: botón "Extract" ─► AJAX grs_extract_reviews (place_id, data_id, limit 10|15)
  ├─ sin data_id: engine=google_maps place_id→data_id (1 crédito)
  │    └─ guarda place_id+data_id en grs_settings
  ├─ extract_all_reviews(data_id, limit, sort=newestFirst)
  │    └─ pagina con next_page_token hasta `limit` (1 crédito POR PÁGINA)
  └─ process_reviews_response()
       ├─ guarda business_name/rating/total en grs_settings
       ├─ filtra rating === 5 (hardcodeado)
       ├─ descarta reseñas sin texto
       ├─ time = strtotime(iso_date), FALLBACK time() ← contamina el orden
       └─ GRS_Database::save_reviews(place_id, ...)  upsert por (place_id, review_id)

Cron cada 30 días: grs_cron_refresh_reviews
  ├─ GRS_Database::delete_all_reviews(place_id)   ← BORRA PRIMERO
  └─ extract_all_reviews(data_id, 15) + process   ← si falla: tabla vacía
```

### Pipeline B - Google Places (legacy, aún vivo)

`grs_get_reviews()` en [api-handler.php](includes/api-handler.php): lee el transient `grs_reviews`; si expiró, llama a `maps.googleapis.com/place/details` (devuelve **máximo 5 reseñas**, las que Google elige), ordena en PHP, filtra por `min_rating` y cachea **30 días**. Solo lo invoca el fallback del frontend.

### Render (frontend)

```
[google_reviews_slider]
  ├─ place_id = grs_settings['grs_place_id']
  ├─ reviews = GRS_Database::get_reviews(place_id, min_rating, LIMIT 50)
  │            ORDER BY time DESC
  ├─ si tabla vacía Y sin lock de 15 min:
  │    ├─ transient grs_reviews (¡datos de hasta 30 días, del pipeline B!)
  │    │    └─ save_reviews() ← RE-SIEMBRA la tabla con datos viejos
  │    └─ o fetch en vivo a Google Places → save_reviews()
  ├─ total mostrado: llamada HTTP a Google DENTRO del render (transient 24h)
  │                  > grs_total_reviews (SerpAPI) > COUNT de la tabla
  └─ por reseña: muestra relative_time_description GUARDADO
                 ("a month ago" congelado al momento de extraer)
```

### Esquema por capa y dónde se pierde o transforma

| Campo | SerpAPI (asunción, ver §9) | Tabla | Render | Pérdida/transformación |
|---|---|---|---|---|
| id de reseña | `review_id` | `review_id` | - | fallback A: `md5(user.name+iso_date)`; fallback B: `md5(author_name+time)`. **Claves distintas por pipeline → duplicados** |
| fecha | `iso_date` (ISO-8601) | `time int(11)` | `relative_time_description` congelado | fallback `time()` inventa fechas; render ignora el timestamp real |
| texto | `snippet` / `extracted_snippet.original` | `text` | esc_html | sin texto → descartada (nunca llega a la tabla) |
| rating | `rating` | `rating` | estrellas dashicons | solo se guardan `=== 5`; el setting `min_rating` queda decorativo |
| autor | `user.name/link/thumbnail` | `author_name/url/profile_photo_url` | esc | avatar fallback apunta a `assets/default-avatar.png`, **archivo que no existe** |
| idioma | - | `language` | - | hardcodeado `'en'` |
| respuesta del dueño | `response.snippet/date` | columnas propias | - | se guarda, nunca se muestra |

## 5. BUG 1 - Causa raíz con evidencia

El síntoma "el admin se ve actualizado, el frontend muestra reseñas viejas" no tiene una sola causa: es una cadena de cuatro defectos que se activan en distintos escenarios. Backend y frontend SÍ leen la misma tabla ([reviews-manager.php:624](includes/reviews-manager.php#L624) y [shortcode.php:81](includes/shortcode.php#L81) llaman ambos a `GRS_Database::get_reviews`), así que la desincronización viene de lo que rodea a esa tabla.

### CR-1 (diseño): el pipeline legacy re-siembra la tabla con datos viejos

Evidencia: [shortcode.php:84-117](includes/shortcode.php#L84-L117). Cuando la tabla queda vacía, el frontend lee el transient `grs_reviews` (escrito por el pipeline Google Places, TTL 30 días, máximo 5 reseñas de la era anterior) y **las vuelve a insertar en la tabla** vía `save_reviews()`. Si el transient expiró, hace un fetch en vivo a Google Places y guarda eso.

Consecuencia exacta del síntoma: el admin extrae reseñas frescas por SerpAPI, algo vacía la tabla (ver CR-2), el siguiente visitante re-siembra reseñas viejas de Google Places. El admin entra a su panel, ve el historial de extracción "success" reciente y la tabla re-poblada mezclada; el frontend ya renderizó (y cacheó, ver CR-4) las viejas. Además los `review_id` del pipeline B se derivan con otra fórmula (`md5(author_name . time)`, [database-handler.php:133](includes/database-handler.php#L133)) que nunca colisiona con los `review_id` reales de SerpAPI: la misma reseña puede existir dos veces con claves distintas. El dedupe por `UNIQUE (place_id, review_id)` no puede atrapar eso.

### CR-2 (gatillo): borrar antes de traer

Evidencia: [google-reviews-slider.php:114-125](google-reviews-slider.php#L114-L125). El cron mensual ejecuta `delete_all_reviews()` y DESPUÉS llama a la API. Si SerpAPI falla, devuelve vacío o el sitio se queda sin key (como ahora, que la key hardcodeada se retiró), la tabla queda en cero y se activa CR-1. El botón "Delete & Refresh All" del admin tiene el mismo patrón en dos llamadas AJAX separadas ([reviews-manager.php:393-459](includes/reviews-manager.php#L393-L459)): si la segunda falla, tabla vacía. Esto viola directamente el requerimiento R1 ("si la API falla, NO borres las reseñas existentes").

### CR-3 (percepción): fechas congeladas en el render

Evidencia: [shortcode.php:255-257](includes/shortcode.php#L255-L257). El slider muestra `relative_time_description`, un string tipo "a month ago" **capturado en el momento de la extracción** y nunca recalculado. Una reseña extraída en enero dirá "hace una semana" para siempre. El admin, en cambio, muestra la fecha real derivada del timestamp ([reviews-manager.php:673](includes/reviews-manager.php#L673)). Por eso ambas pantallas "cuentan historias distintas" incluso cuando los datos son idénticos. Este defecto está activo en el 100 % de las instalaciones, sin necesidad de fallos de API.

### CR-4 (infraestructura): nadie purga el caché de página

El HTML del shortcode se genera server-side. Tras un sync, el plugin borra sus transients ([serpapi-handler.php:277-279](includes/serpapi-handler.php#L277-L279)) pero no dispara ninguna purga de page cache (LiteSpeed, WP Rocket, Cloudflare, caché del hosting). Las páginas de admin nunca pasan por page cache, así que el admin siempre ve datos frescos mientras el frontend sirve HTML congelado hasta que el caché expire. Esto no se puede demostrar solo con el código (depende del hosting), pero la ausencia total de integración de purga es el gap verificable. Marcado como causa de despliegue, no de lógica.

### CR-5 (borde): claves de almacenamiento divergentes

Evidencia: [serpapi-handler.php:461](includes/serpapi-handler.php#L461) guarda bajo `$storage_id = place_id ?: data_id`, tomado de los inputs vivos del formulario, mientras el frontend lee `grs_settings['grs_place_id']` ([shortcode.php:61](includes/shortcode.php#L61)). El flujo normal sincroniza la opción, pero si el admin envía un `data_id` manual (o un place_id que empieza con `0x`), las reseñas se guardan bajo una clave que el frontend jamás consulta.

### Veredicto

La causa raíz estructural es que **la migración a SerpAPI nunca retiró el pipeline anterior ni sus artefactos**: el fallback legacy (CR-1) y las fechas congeladas (CR-3) son restos de la era Google Places/Slick, y el cron destructivo (CR-2) convierte cualquier fallo de red en el detonador. El fix correcto no es otro parche sobre el fallback (el circuit breaker de v2.7.11 ya fue un parche sobre este mismo agujero): es eliminar el pipeline B, hacer el sync transaccional (traer primero, reemplazar después), derivar la fecha visible del timestamp, y purgar/versionar lo que el page cache pueda congelar.

## 6. BUG 2 - Orden de reseñas

La query ya ordena `ORDER BY time DESC` ([database-handler.php:203-212](includes/database-handler.php#L203-L212)), en SQL, como pide el requerimiento. El problema es que la columna `time` está contaminada:

1. [serpapi-handler.php:311-328](includes/serpapi-handler.php#L311-L328): si `iso_date` falta o no parsea, `convert_to_timestamp()` devuelve `time()`, o sea "ahora". Esa reseña brinca al primer lugar como si fuera la más nueva.
2. [database-handler.php:139](includes/database-handler.php#L139): mismo fallback `time()` en la capa de guardado.
3. El pipeline B guarda el `time` unix de Google (correcto), pero al re-sembrar mezcla épocas y fórmulas de `review_id`, así que el orden visible puede intercalar reseñas duplicadas viejas.
4. `strtotime()` sobre ISO-8601 con zona horaria sí produce el instante UTC correcto; la normalización pedida ya ocurre cuando `iso_date` existe. Falta hacerla obligatoria: sin fecha parseable, la reseña debe rechazarse o marcarse, nunca inventar "ahora".

## 7. Estado actual frente a los requerimientos

**R1 (sync mensual):** existe un cron `monthly` de 30 días. Faltan: guard rail de última fecha persistida (si el cron se dispara doble, hace doble fetch), lock contra syncs concurrentes, fetch-antes-de-borrar, rate limit del botón manual, y el panel de estado (hay "next/last" básicos en [reviews-manager.php:53-61](includes/reviews-manager.php#L53-L61), sin estado ok/error/rate-limited). El logging registra la extracción agregada pero no cada llamada HTTP (una extracción de 15 reseñas son 2-3 requests facturables).

**R2 (máximo 10):** hoy el cron pide 15, el admin ofrece 10 o 15, el render lee `LIMIT 50` y la tabla nunca se poda (el upsert acumula). La paginación trae páginas completas de SerpAPI aunque ya haya suficientes. Nada garantiza "las 10 más recientes": el corte es "las primeras `limit` que devuelva la paginación en orden newestFirst", que tras el filtro de 5 estrellas y texto puede dejar menos de 10 o gastarse créditos de más.

## 8. Tabla de hallazgos

| # | Sev. | Área | Hallazgo | Evidencia |
|---|---|---|---|---|
| H1 | Crítico | Datos | Pipeline legacy re-siembra la tabla con reseñas viejas (CR-1) | [shortcode.php:84-117](includes/shortcode.php#L84-L117) |
| H2 | Crítico | Datos | Cron y "Delete & Refresh" borran antes de traer; fallo de API = slider vacío o re-sembrado (CR-2) | [google-reviews-slider.php:114-125](google-reviews-slider.php#L114-L125) |
| H3 | Crítico | Seguridad | SerpAPI key (y token Outscraper) expuestos en historial git público; rotación pendiente | historial de git |
| H4 | Alto | Datos | Fechas visibles congeladas: render usa string relativo de la extracción, no el timestamp (CR-3) | [shortcode.php:255-257](includes/shortcode.php#L255-L257) |
| H5 | Alto | Datos | `time()` como fallback de fecha rompe el orden newest-first (BUG 2) | [serpapi-handler.php:327](includes/serpapi-handler.php#L327) |
| H6 | Alto | Rendimiento | Llamada HTTP a Google Places dentro del render del shortcode al expirar transient de 24 h | [shortcode.php:127-143](includes/shortcode.php#L127-L143) |
| H7 | Alto | Rendimiento | dashicons + script dummy con nonce encolados en TODAS las páginas del sitio | [google-reviews-slider.php:133-145](google-reviews-slider.php#L133-L145) |
| H8 | Alto | Datos | Sin purga de page cache tras sync; frontend sirve HTML congelado (CR-4) | ausencia de integración |
| H9 | Medio | Datos | `review_id` con dos fórmulas de fallback distintas → duplicados indetectables | [serpapi-handler.php:249](includes/serpapi-handler.php#L249) vs [database-handler.php:133](includes/database-handler.php#L133) |
| H10 | Medio | Seguridad | `grs_test_api` sin nonce (CSRF gasta crédito); `grs_test_ajax` sin nonce ni capability | [serpapi-handler.php:476-496](includes/serpapi-handler.php#L476-L496) |
| H11 | Medio | Robustez | Avatar fallback apunta a `assets/default-avatar.png`, archivo inexistente; `onerror` puede reintentar en bucle | [shortcode.php:260](includes/shortcode.php#L260) |
| H12 | Medio | Frontend | Botones prev/next con doble binding (navigation de Swiper + listeners manuales click/touchend): probable doble avance por clic; verificar en runtime | [swiper-init.js:57-127](js/swiper-init.js#L57-L127) |
| H13 | Medio | Datos | Sync escribe business info dentro de `grs_settings` con `update_option` completo: carrera con guardado de settings | [serpapi-handler.php:204-214](includes/serpapi-handler.php#L204-L214) |
| H14 | Medio | Producto | Filtro `rating === 5` hardcodeado deja el setting `min_rating` y el filtro del admin como decoración | [serpapi-handler.php:226-228](includes/serpapi-handler.php#L226-L228) |
| H15 | Medio | Limpieza | `uninstall.php` dropea `grs_extraction_log`; la tabla real es `grs_extraction_history` (queda huérfana); no borra `grs_version` ni `grs_db_version` | [uninstall.php:22](uninstall.php#L22) |
| H16 | Medio | Código muerto | `outscraper-api.php` + `class_alias` = fatal error latente; `js/script.js`, `css/style.css`, `assets/slick/` sin uso | [serpapi-handler.php:397](includes/serpapi-handler.php#L397) |
| H17 | Medio | A11y | Sin `keyboard` en Swiper, sin `prefers-reduced-motion`, estrellas con icon-font en vez de SVG | [swiper-init.js](js/swiper-init.js), [shortcode.php:212-215](includes/shortcode.php#L212-L215) |
| H18 | Medio | Compat | Swiper 11 desde CDN jsdelivr sin fallback local; dependencia de terceros en producción | [shortcode.php:37](includes/shortcode.php#L37) |
| H19 | Bajo | i18n | Text domain declarado `google-reviews-slider` pero los `__()` usan `grs`; casi todo hardcodeado en inglés | [admin-page.php:33](includes/admin-page.php#L33) |
| H20 | Bajo | Robustez | `strlen()` cuenta bytes: truncado a "120 caracteres" corta antes con emoji/multibyte | [shortcode.php:253](includes/shortcode.php#L253) |
| H21 | Bajo | Seguridad | Admin JS inserta `response.data` y datos de API en HTML sin escapar (superficie admin-only) | [reviews-manager.php:374](includes/reviews-manager.php#L374) |
| H22 | Bajo | Higiene | `build/`, `releases/` y scripts de debug comiteados al repo; `readme.txt` describe Outscraper | raíz del repo |
| H23 | Bajo | Compat | Schedule genérico `monthly` puede chocar con otro plugin que registre el mismo nombre con otro intervalo | [google-reviews-slider.php:80-87](google-reviews-slider.php#L80-L87) |
| H24 | Bajo | Datos | `time int(11)` desborda en 2038; `date('F Y')` sin localizar ni zona del sitio | [database-handler.php:47](includes/database-handler.php#L47) |

## 9. Suposiciones marcadas (no verificables desde el código)

1. **Forma de la respuesta de SerpAPI**: los campos `reviews[].iso_date`, `snippet`, `extracted_snippet.original`, `user.{name,link,thumbnail,local_guide,reviews}`, `response.{snippet,date}`, `place_info` y `serpapi_pagination.next_page_token` se infieren del código de mapeo. No hay una respuesta real capturada en el repo para confirmarlos.
2. **Tamaño de página**: asumo ~10 reseñas por request en `google_maps_reviews` (por eso pedir 15 cuesta 2 créditos). Sin confirmar contra la doc de SerpAPI.
3. **Page cache del hosting**: CR-4 depende de la configuración del servidor (LiteSpeed/Hostinger/CDN). El gap de purga es real; su impacto exacto, no medible desde aquí.
4. **H12 (doble avance de flechas)**: diagnóstico por lectura de código; requiere confirmación en navegador.

## 10. Decisiones de diseño

Pendiente: esta sección se completa en la fase de implementación, junto con el rediseño del slider (SVG para estrellas, variables CSS para theming, jerarquía tipográfica, `prefers-reduced-motion`). El diseño actual depende de dashicons, muestra un "EXCELLENT" hardcodeado y no expone ningún punto de personalización.

## 11. Problemas sin resolver

Pendiente: se documenta aquí lo que sobreviva al loop de QA.
