(function() {
    var serverTs = window.APP_CONFIG && window.APP_CONFIG.serverTimestamp;
    var offset = serverTs ? (serverTs * 1000 - Date.now()) : 0;

    function pad(n) { return n < 10 ? '0' + n : '' + n; }

    function formatTime(date) {
        var h = date.getHours();
        var m = date.getMinutes();
        var ampm = h >= 12 ? 'PM' : 'AM';
        h = h % 12 || 12;
        return pad(h) + ':' + pad(m) + ' ' + ampm;
    }

    function updateAllClocks() {
        var now = new Date(Date.now() + offset);
        var timeStr = formatTime(now);
        document.querySelectorAll('.js-clock').forEach(function(el) {
            el.textContent = timeStr;
        });
    }

    updateAllClocks();
    setInterval(updateAllClocks, 30000);
})();
