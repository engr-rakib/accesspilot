/**
 * noc_tooltip.js — Centralized tooltip system for AccessPilot
 *
 * Usage:
 *   Add data-noc-tip="Your tooltip text" to any element.
 *   Tooltip appears on hover with sticky positioning.
 *
 * Configuration (set before script loads):
 *   window.NocTooltipConfig = {
 *       bg: 'rgba(15,23,42,0.98)',     // background (less transparent)
 *       color: '#fff',                  // text
 *       fontSize: '0.72rem',
 *       padding: '6px 10px',
 *       borderRadius: '6px',
 *       border: '1px solid rgba(255,255,255,0.1)',
 *       shadow: '0 4px 15px rgba(0,0,0,0.5)',
 *       maxWidth: '260px',
 *       gap: 6,                          // px between element and tooltip
 *       arrowSize: 5,                    // px
 *       arrowColor: 'rgba(15,23,42,0.98)',
 *       zIndex: 99999
 *   };
 */
(function(){
    var C = Object.assign({
        bg: 'rgba(15, 23, 42, 0.98)',
        color: '#fff',
        fontSize: '0.72rem',
        padding: '6px 10px',
        borderRadius: '6px',
        border: '1px solid rgba(255,255,255,0.1)',
        shadow: '0 4px 15px rgba(0,0,0,0.5)',
        maxWidth: '260px',
        gap: 6,
        arrowSize: 5,
        arrowColor: 'rgba(15,23,42,0.98)',
        zIndex: 99999
    }, window.NocTooltipConfig || {});

    var tip = null, arr = null;

    function getEls(){
        if(!tip){
            tip = document.createElement('div');
            arr = document.createElement('div');
            tip.style.cssText = 'position:fixed;z-index:'+C.zIndex+';background:'+C.bg+';color:'+C.color+';font-family:var(--primary-font,"Segoe UI",sans-serif);font-size:'+C.fontSize+';padding:'+C.padding+';border-radius:'+C.borderRadius+';border:'+C.border+';box-shadow:'+C.shadow+';line-height:1.3;max-width:'+C.maxWidth+';text-align:center;pointer-events:none;white-space:normal;display:none;';
            arr.style.cssText = 'position:fixed;z-index:'+C.zIndex+';width:0;height:0;border:'+C.arrowSize+'px solid transparent;border-top-color:'+C.arrowColor+';pointer-events:none;display:none;';
            document.body.appendChild(tip);
            document.body.appendChild(arr);
        }
        return {tip:tip, arrow:arr};
    }

    function show(e, el){
        var t = getEls(), txt = el.getAttribute('data-noc-tip');
        if(!txt) return;
        t.tip.textContent = txt;
        var r = el.getBoundingClientRect();
        var cx = r.left + r.width/2;
        t.tip.style.display = 'block';
        t.tip.style.opacity = '0';
        t.tip.style.left = '0px';
        t.tip.style.top = '0px';
        var tw = t.tip.offsetWidth, th = t.tip.offsetHeight;
        var aboveTop = r.top - th - C.gap;
        var belowTop = r.bottom + C.gap;
        var useBelow = aboveTop < 4;
        var ttop = useBelow ? belowTop : aboveTop;
        var l = Math.max(4, Math.min(cx - tw/2, window.innerWidth - tw - 4));
        t.tip.style.left = l + 'px';
        t.tip.style.top = ttop + 'px';
        t.tip.style.opacity = '1';
        if(arr){
            arr.style.display = 'block';
            arr.style.left = (cx - C.arrowSize) + 'px';
            arr.style.borderTopColor = 'transparent';
            arr.style.borderBottomColor = 'transparent';
            if(useBelow){
                arr.style.borderBottomColor = C.arrowColor;
                arr.style.top = (r.bottom - 1) + 'px';
            } else {
                arr.style.borderTopColor = C.arrowColor;
                arr.style.top = (r.top - C.arrowSize + 1) + 'px';
            }
        }
    }

    function hide(){
        if(tip){ tip.style.display = 'none'; }
        if(arr){
            arr.style.display = 'none';
            arr.style.borderTopColor = 'transparent';
            arr.style.borderBottomColor = 'transparent';
        }
    }

    function bindAll(){
        document.querySelectorAll('[data-noc-tip]').forEach(function(el){
            if(el._nt) return;
            el._nt = true;
            el.addEventListener('mouseenter', function(e){ show(e, this); });
            el.addEventListener('mouseleave', hide);
        });
    }

    function init(){
        bindAll();
    }

    if(document.readyState==='loading'){
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    document.addEventListener('spaContentUpdated', init);

    var obs = new MutationObserver(init);
    obs.observe(document.body, {childList:true, subtree:true});

    window.NocTooltip = {
        init: init,
        config: C,
        show: show,
        hide: hide,
        showTempText: function(el, text, duration){
            var t = getEls();
            t.tip.textContent = text;
            var r = el.getBoundingClientRect();
            var cx = r.left + r.width/2;
            t.tip.style.display = 'block';
            t.tip.style.opacity = '0';
            t.tip.style.left = '0px';
            t.tip.style.top = '0px';
            var tw = t.tip.offsetWidth, th = t.tip.offsetHeight;
            var aboveTop = r.top - th - C.gap;
            var belowTop = r.bottom + C.gap;
            var useBelow = aboveTop < 4;
            var ttop = useBelow ? belowTop : aboveTop;
            var l = Math.max(4, Math.min(cx - tw/2, window.innerWidth - tw - 4));
            t.tip.style.left = l + 'px';
            t.tip.style.top = ttop + 'px';
            t.tip.style.opacity = '1';
            if(arr){
                arr.style.display = 'block';
                arr.style.left = (cx - C.arrowSize) + 'px';
                arr.style.borderTopColor = 'transparent';
                arr.style.borderBottomColor = 'transparent';
                if(useBelow){
                    arr.style.borderBottomColor = C.arrowColor;
                    arr.style.top = (r.bottom - 1) + 'px';
                } else {
                    arr.style.borderTopColor = C.arrowColor;
                    arr.style.top = (r.top - C.arrowSize + 1) + 'px';
                }
            }
            clearTimeout(t.tip._ht);
            if(duration > 0) t.tip._ht = setTimeout(hide, duration);
        }
    };
})();
