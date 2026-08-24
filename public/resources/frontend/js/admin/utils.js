function escapeHTML(str) {
    if (typeof str !== 'string') return '';
    return str.replace(/[&<>"'`]/g, match => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;', '`': '&#x60;' }[match]));
}

function parseCsvLine(str) {
    var result = [], cur = '', inQ = false;
    for (var i = 0; i < str.length; i++) {
        var ch = str[i];
        if (inQ) {
            if (ch === '"') {
                if (i + 1 < str.length && str[i + 1] === '"') { cur += '"'; i++; }
                else { inQ = false; }
            } else { cur += ch; }
        } else {
            if (ch === '"') { inQ = true; }
            else if (ch === ',') { result.push(cur); cur = ''; }
            else { cur += ch; }
        }
    }
    result.push(cur);
    return result;
}

function csvToHtmlTable(csvString) {
    const lines = csvString.trim().split('\n');
    if (lines.length === 0) return '<p>No data to display.</p>';

    let html = '<table class="table app-data-table log-table dynamic-report-table"><thead><tr>';

    const headers = parseCsvLine(lines[0]).map(h => escapeHTML(h.trim()));
    headers.forEach(header => {
        html += `<th>${header}</th>`;
    });
    html += '</tr></thead><tbody>';

    for (let i = 1; i < lines.length; i++) {
        if (lines[i].trim() === '') continue;
        const cols = parseCsvLine(lines[i]).map(c => escapeHTML(c.trim()));
        html += '<tr>';
        cols.forEach(col => {
            html += `<td title="${col.replace(/"/g, '&quot;')}">${col}</td>`;
        });
        html += '</tr>';
    }

    html += '</tbody></table>';
    return html;
}

// Centralized feedback message rendering for consistent font/size across all actions
function renderFeedbackMessage(text) {
    if (!text) return '';
    return `<div style="margin:0;font-family:'Roboto',sans-serif;font-size:0.85rem;white-space:pre-wrap;line-height:1.6;word-break:break-word;">${styleFeedbackMessage(text)}</div>`;
}
window.renderFeedbackMessage = renderFeedbackMessage;

function styleFeedbackMessage(msg) {
    if (!msg) return '';
    return msg.split('\n').map(line => {
        const t = line.trim();
        if (/^(>>\s*)?Processed:\s*\d/.test(t)) {
            const pd = (t.match(/Processed:\s*(\d+)/) || [])[1];
            const sc = (t.match(/Success:\s*(\d+)/) || [])[1];
            const sk = (t.match(/Skipped:\s*(\d+)/) || [])[1];
            const fl = (t.match(/Failed:\s*(\d+)/) || [])[1];
            const badges = [];
            if (pd) badges.push('<span class="status-badge status-info">Processed: ' + pd + '</span>');
            if (sc) badges.push('<span class="status-badge ' + (+sc > 0 ? 'status-success' : 'status-info') + '">Success: ' + sc + '</span>');
            if (sk) badges.push('<span class="status-badge ' + (+sk > 0 ? 'status-warning' : 'status-info') + '">Skipped: ' + sk + '</span>');
            if (fl) badges.push('<span class="status-badge ' + (+fl > 0 ? 'status-failed' : 'status-info') + '">Failed: ' + fl + '</span>');
            return '<div>' + badges.join(' ') + '</div>';
        }
        return escapeHTML(line);
    }).join('<br>');
}
window.styleFeedbackMessage = styleFeedbackMessage;

// Helper function to clear any existing report buttons and deep-check credential section
function clearReportButtons() {
    const existingButtons = document.getElementById('adHrmsReportButtons');
    if (existingButtons) { existingButtons.remove(); }
    const existingCred = document.getElementById('deepCheckCredentialSection');
    if (existingCred) { existingCred.remove(); }
    const existingExport = document.getElementById('exportReportContainer');
    if (existingExport) { existingExport.remove(); }
    const existingSecEvents = document.getElementById('secEventsCloseWrapper');
    if (existingSecEvents) { existingSecEvents.remove(); }
    // Remove download + close buttons added to header by report actions
    const syncActions = document.getElementById('syncMappingHeaderActions');
    if (syncActions) { syncActions.remove(); }
}

// EFFECT: loading dots animation | Purpose: 3 colored bouncing dots for loading state
function showLoadingAnimation(element) {
    const colors = ['#1976D2', '#AA3A46', '#1B5E20'];
    element.innerHTML = `
        <div class="alert-loading-content">
            <div class="loading-dots">
                <span style="background-color: ${colors[0]};"></span>
                <span style="background-color: ${colors[1]};"></span>
                <span style="background-color: ${colors[2]};"></span>
            </div>
            <div class="loading-text">Your request is underway...</div>
        </div>`;
}

// Attach to window for global access
window.showLoadingAnimation = showLoadingAnimation;

let actionCardHideTimeout = null;
function autoHideActionCard(delay = 45000) {
    const container = document.getElementById('actionTakenCardContainer');
    if (!container) return;

    if (actionCardHideTimeout) {
        clearTimeout(actionCardHideTimeout);
    }

    actionCardHideTimeout = setTimeout(() => {
        container.classList.remove('visible');
    }, delay);
}