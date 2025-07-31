/**
 * Admin styles for Google Reviews Slider
 * Version: 2.0.0
 */

/* Spinning animation for dashicons */
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.dashicons.spinning {
    animation: spin 2s linear infinite;
    display: inline-block;
}

/* Hide the reviews manager initially on page load */
.grs-reviews-manager {
    opacity: 0;
    animation: fadeIn 0.5s ease-in forwards;
    animation-delay: 0.3s;
}

@keyframes fadeIn {
    to {
        opacity: 1;
    }
}

/* Style improvements for the admin page */
.grs-admin-page .notice {
    margin: 10px 0;
}

.grs-cache-section {
    max-width: 800px;
}

#clear-cache-btn {
    vertical-align: middle;
}

#cache-message {
    display: inline-block;
    vertical-align: middle;
}

/* Place finder improvements */
.place-finder {
    max-width: 800px;
}

#pac-input {
    box-sizing: border-box;
    margin-bottom: 10px;
}

#map {
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

/* Reviews manager specific styles */
.grs-reviews-manager h3 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 2px solid #ddd;
}

.grs-reviews-manager h4 {
    margin-top: 0;
    color: #23282d;
}

/* Responsive improvements */
@media screen and (max-width: 782px) {
    .grs-stats-grid {
        grid-template-columns: repeat(2, 1fr) !important;
    }
    
    .grs-extract-controls {
        flex-direction: column;
        align-items: stretch !important;
    }
    
    .grs-extract-controls > * {
        width: 100%;
    }
    
    .grs-table-controls {
        flex-direction: column;
        align-items: stretch !important;
    }
    
    .review-text-cell {
        max-width: 200px !important;
    }
}

/* Table improvements */
.grs-reviews-table-section table img {
    vertical-align: middle;
}

.grs-reviews-table-section .dashicons-star-filled {
    font-size: 16px;
    width: 16px;
    height: 16px;
    line-height: 1;
}

/* Button states */
.button:disabled {
    cursor: not-allowed;
    opacity: 0.6;
}

/* Review text expansion */
.review-text-cell {
    position: relative;
    transition: all 0.3s ease;
}

.review-text-cell.review-text-full {
    max-width: none !important;
    white-space: normal !important;
}

/* Loading states */
.grs-loading-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}

/* API usage display */
.grs-api-usage table {
    margin-top: 10px;
}

.grs-api-usage table td {
    padding: 5px 10px;
}

.grs-api-usage table td:first-child {
    font-weight: 600;
    width: 40%;
}

/* Success animations */
@keyframes successPulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

.grs-status-message.success {
    animation: successPulse 0.5s ease-in-out;
}

/* Improved changelog styling */
.grs-changelog {
    position: relative;
    overflow: hidden;
}

.grs-changelog::before {
    content: "🎉";
    position: absolute;
    top: -10px;
    right: -10px;
    font-size: 60px;
    opacity: 0.1;
    transform: rotate(15deg);
}

/* Version info */
.grs-version-info {
    background: #f0f0f0;
    padding: 2px 8px;
    border-radius: 3px;
    font-weight: normal;
}

/* Details/Summary styling */
details summary {
    cursor: pointer;
    padding: 5px 0;
    outline: none;
}

details summary:hover {
    color: #0073aa;
}

details[open] summary {
    margin-bottom: 10px;
    padding-bottom: 10px;
    border-bottom: 1px solid #ddd;
}