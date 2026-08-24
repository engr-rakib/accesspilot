<?php
/**
 * resources/views/pages/auth/profile_view.php
 * 
 * Fully Dynamic User Profile Hub.
 * Features: Account Info, Theme Personalization, Password Management, and Secure Avatar Storage.
 */
if (!defined('_CORE_ADMIN_')) {
    die('Direct access not permitted');
}

$username = $_SESSION['username'] ?? 'Administrator';
$clientIP = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
$currentTheme = $_COOKIE['selectedTheme'] ?? 'theme-natural-green';
$currentAvatar = $_SESSION['avatar'] ?? '';

$profileFullName = '';
$profileEmail = '';
$profileMobile = '';
if (function_exists('readUsers')) {
    $allUsers = readUsers();
    if (isset($allUsers[$username])) {
        $profileFullName = $allUsers[$username]['full_name'] ?? '';
        $profileEmail = $allUsers[$username]['email'] ?? '';
        $profileMobile = $allUsers[$username]['mobile'] ?? '';
        if (empty($currentAvatar) && !empty($allUsers[$username]['avatar'])) {
            $currentAvatar = $allUsers[$username]['avatar'];
        }
    }
}

// Avatar URL logic
$avatarUrl = $baseURL . $app_config['app_info']['logo_path'];
if (!empty($currentAvatar)) {
    $avatarUrl = $baseURL . '/api/index.php?endpoint=get_avatar&file=' . urlencode($currentAvatar);
}

$browser = "Unknown";
if (preg_match('/Edg\/([0-9.]+)/i', $userAgent)) $browser = "Edge";
elseif (preg_match('/Chrome\/([0-9.]+)/i', $userAgent)) $browser = "Chrome";
elseif (preg_match('/Firefox\/([0-9.]+)/i', $userAgent)) $browser = "Firefox";
elseif (preg_match('/Safari\/([0-9.]+)/i', $userAgent)) $browser = "Safari";
elseif (preg_match('/MSIE|Trident/i', $userAgent)) $browser = "IE";
?>

<div class="profile-view slide-in-top" id="profileWorkspace">
    <input type="file" id="avatarInput" accept="image/*" class="d-none">

    <!-- Profile Card (full width) -->
    <div class="profile-card">
        <div class="profile-header">
            <div class="profile-avatar-wrap" id="triggerAvatarUpload" title="Upload Square Image (Max 1MB)">
                <div class="profile-avatar-circle">
                    <img src="<?= $avatarUrl ?>" alt="Avatar" id="displayAvatar">
                </div>
                <div class="profile-avatar-overlay"><i class="fas fa-camera"></i></div>
            </div>
            <h4 class="profile-name"><?= htmlspecialchars($username) ?></h4>
            <p class="profile-role">Administrator</p>
        </div>
        <div class="profile-card-body">
            <form id="updateProfileDetailsForm">
                <div class="row">
                    <div class="col-md-4 mb-2">
                        <label class="profile-form-label">Full Name</label>
                        <input type="text" class="form-control" id="profile_full_name" name="full_name" value="<?= htmlspecialchars($profileFullName) ?>" placeholder="Full name">
                    </div>
                    <div class="col-md-4 mb-2">
                        <label class="profile-form-label">Email</label>
                        <input type="email" class="form-control" id="profile_email" name="email" value="<?= htmlspecialchars($profileEmail) ?>" placeholder="Email">
                    </div>
                    <div class="col-md-4 mb-2">
                        <label class="profile-form-label">Mobile</label>
                        <input type="text" class="form-control" id="profile_mobile" name="mobile" value="<?= htmlspecialchars($profileMobile) ?>" placeholder="Mobile">
                    </div>
                </div>
                <button type="submit" class="profile-card-action-btn">Save</button>
            </form>
            <div class="profile-toggles-border-top mt-2">
                <button class="btn btn-sm btn-outline-primary" type="button" id="profileChangePasswordBtn" onclick="document.getElementById('profilePasswordForm').classList.toggle('d-none');"><i class="fas fa-key"></i> Change Password</button>
                <div class="d-none mt-2" id="profilePasswordForm">
                    <form id="changePasswordForm">
                        <div class="row">
                            <div class="col-md-4 mb-2">
                                <label class="profile-form-label">Current Password</label>
                                <div class="password-input-wrapper">
                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                    <button type="button" class="password-toggle-btn" data-target="current_password"><i class="fas fa-eye"></i></button>
                                </div>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="profile-form-label">New Password</label>
                                <div class="password-input-wrapper">
                                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                                    <button type="button" class="password-toggle-btn" data-target="new_password"><i class="fas fa-eye"></i></button>
                                </div>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="profile-form-label">Confirm New Password</label>
                                <div class="password-input-wrapper">
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    <button type="button" class="password-toggle-btn" data-target="confirm_password"><i class="fas fa-eye"></i></button>
                                </div>
                            </div>
                        </div>
                        <div class="password-meter-wrap mb-1">
                            <div class="password-meter-bar" id="passwordMeterBar"></div>
                        </div>
                        <div class="password-meter-label mb-2" id="passwordMeterLabel">Strength: Calculating...</div>
                        <div class="profile-complexity-box" id="passwordComplexityWrapper">
                            <div class="password-rules-grid">
                                <div class="password-rule d-flex align-items-center gap-1" data-rule="length"><i class="fas fa-circle text-muted"></i><span>8+ characters</span></div>
                                <div class="password-rule d-flex align-items-center gap-1" data-rule="upper"><i class="fas fa-circle text-muted"></i><span>Uppercase letter</span></div>
                                <div class="password-rule d-flex align-items-center gap-1" data-rule="number"><i class="fas fa-circle text-muted"></i><span>Numerical digit</span></div>
                                <div class="password-rule d-flex align-items-center gap-1" data-rule="match"><i class="fas fa-circle text-muted"></i><span>Confirmation match</span></div>
                            </div>
                        </div>
                        <button type="submit" class="profile-card-action-btn" id="submitChangePassword">Update Password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-lg-6">
            <div class="profile-card">
                <div class="profile-card-body">
                    <h6 class="profile-section-title"><i class="fas fa-chart-simple"></i>Quick Stats</h6>
                    <div class="profile-stat-grid">
                        <div class="profile-stat-box"><div class="profile-stat-num" id="statActions">0</div><div class="profile-stat-lbl">Total Actions</div></div>
                        <div class="profile-stat-box"><div class="profile-stat-num" id="statLogins">0</div><div class="profile-stat-lbl">Total Logins</div></div>
                        <div class="profile-stat-box"><div class="profile-stat-num" id="statLogin24h">0</div><div class="profile-stat-lbl">Logins (24h)</div></div>
                    </div>
                    <div class="profile-toggles-border-top mt-2" id="statDetails" style="display:none;">
                        <div class="profile-session-row" style="flex-wrap:wrap;gap:4px 12px;">
                            <span style="font-size:0.72rem;color:var(--text-muted);">AD Actions: <strong id="statAdActions" style="color:var(--primary-color);">0</strong></span>
                            <span style="font-size:0.72rem;color:var(--text-muted);">Web Actions: <strong id="statWebActions" style="color:var(--primary-color);">0</strong></span>
                        </div>
                        <div class="profile-session-row" style="flex-wrap:wrap;gap:2px 10px;padding-top:2px;" id="statAdBreakdown"></div>
                    </div>
                    <div class="profile-toggles-border-top mt-2">
                        <div class="profile-session-row">
                            <span class="activity-dot" style="background:#22c55e;"></span>
                            <span class="profile-session-info"><?= htmlspecialchars($clientIP) ?> / <?= htmlspecialchars($browser) ?></span>
                            <span class="profile-session-time font-tech" id="profileSessionTime">0:00</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Theme Card -->
            <div class="profile-card">
                <div class="profile-card-body">
                    <h6 class="profile-section-title"><i class="fas fa-palette"></i>Theme</h6>
                    <div class="profile-theme-grid">
                        <div class="theme-option-card <?= ($currentTheme === 'theme-corporate-blue') ? 'active' : '' ?>" data-theme="theme-corporate-blue">
                            <div class="theme-swatch" style="background-color:#2196F3;"></div>
                            <span class="theme-label">Corporate</span>
                            <i class="fas fa-check theme-check"></i>
                        </div>
                        <div class="theme-option-card <?= ($currentTheme === 'theme-red') ? 'active' : '' ?>" data-theme="theme-red">
                            <div class="theme-swatch" style="background-color:#D32F2F;"></div>
                            <span class="theme-label">Red</span>
                            <i class="fas fa-check theme-check"></i>
                        </div>
                        <div class="theme-option-card <?= ($currentTheme === 'theme-natural-green') ? 'active' : '' ?>" data-theme="theme-natural-green">
                            <div class="theme-swatch" style="background-color:#4CAF50;"></div>
                            <span class="theme-label">Natural</span>
                            <i class="fas fa-check theme-check"></i>
                        </div>
                        <div class="theme-option-card <?= ($currentTheme === 'theme-matte-black') ? 'active' : '' ?>" data-theme="theme-matte-black">
                            <div class="theme-swatch" style="background-color:#121212;"></div>
                            <span class="theme-label">Dark</span>
                            <i class="fas fa-check theme-check"></i>
                        </div>
                        <div class="theme-option-card <?= ($currentTheme === 'theme-glass-aura') ? 'active' : '' ?>" data-theme="theme-glass-aura">
                            <div class="theme-swatch" style="background:linear-gradient(135deg,#a78bfa,#6366f1,#ec4899);"></div>
                            <span class="theme-label">Glass</span>
                            <i class="fas fa-check theme-check"></i>
                        </div>
                        <div class="theme-option-card <?= ($currentTheme === 'theme-white-professional') ? 'active' : '' ?>" data-theme="theme-white-professional">
                            <div class="theme-swatch" style="background:linear-gradient(135deg,#6366f1,#4f46e5);"></div>
                            <span class="theme-label">White</span>
                            <i class="fas fa-check theme-check"></i>
                        </div>
                        <div class="theme-option-card <?= ($currentTheme === 'theme-blue-purple-pro') ? 'active' : '' ?>" data-theme="theme-blue-purple-pro">
                            <div class="theme-swatch" style="background:linear-gradient(135deg,#6366f1,#7c3aed);"></div>
                            <span class="theme-label">Purple</span>
                            <i class="fas fa-check theme-check"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <!-- Notifications Card -->
            <div class="profile-card">
                <div class="profile-card-body">
                    <h6 class="profile-section-title"><i class="fas fa-bell"></i>Notifications</h6>
                    <div class="profile-notif-grid">
                        <div class="profile-notif-item">
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" id="profileShowToasts" checked>
                                <label class="profile-toggle-label" for="profileShowToasts">Toasts</label>
                            </div>
                        </div>
                        <div class="profile-notif-item">
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input pref-toggle" type="checkbox" id="pref_auto_refresh" data-pref="auto_refresh" checked>
                                <label class="profile-toggle-label" for="pref_auto_refresh">Auto-refresh</label>
                            </div>
                        </div>
                        <div class="profile-notif-item">
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input pref-toggle" type="checkbox" id="pref_notifications" data-pref="notifications" checked>
                                <label class="profile-toggle-label" for="pref_notifications">Alerts</label>
                            </div>
                        </div>
                        <div class="profile-notif-item">
                            <div class="d-flex align-items-center" style="gap:4px;">
                                <div class="form-check form-switch mb-0 flex-grow-1">
                                    <input class="form-check-input pref-toggle" type="checkbox" id="pref_sound" data-pref="sound_alerts">
                                    <label class="profile-toggle-label" for="pref_sound">Sound</label>
                                </div>
                                <button class="profile-sound-test-btn" id="profileSoundTestBtn" title="Test Sound"><i class="fas fa-volume-up"></i></button>
                            </div>
                        </div>
                    </div>
                    <div class="profile-toggles-border-top mt-2">
                        <div class="profile-notif-cat-grid" id="profileCategoryPrefs"></div>
                    </div>
                </div>
            </div>

            <div class="profile-card">
                <div class="profile-card-body">
                    <h6 class="profile-section-title"><i class="fas fa-clock-rotate-left"></i>Recent Activity</h6>
                    <div class="profile-activity-list" id="profileActivityList">
                        <div class="activity-empty text-center py-2">No recent activity.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
window.initProfileHub = function() {
    if(window._profileInitialized)return;window._profileInitialized=true;
    var baseUrl = (window.APP_CONFIG && window.APP_CONFIG.baseUrl) || (typeof baseURL === 'string' ? baseURL : '');
    var apiUrl = baseUrl + '/api/index.php?endpoint=profile_action';
    var mgmtUrl = baseUrl + '/api/index.php?endpoint=user_management_action';
    var avatarUrl = baseUrl + '/api/index.php?endpoint=get_avatar&file=';

    function showResult(t,m,s){if(typeof window.displayActionTakenResult==='function'){window.displayActionTakenResult(t,m,s);if(typeof autoHideActionCard==='function')autoHideActionCard();}else{alert(m);}}

    function loadProfile(){
        if(typeof window._csrfToken !== 'string'||!window._csrfToken){setTimeout(loadProfile,50);return;}
        fetch(apiUrl,{method:'POST',body:JSON.stringify({action:'get_profile'})}).then(function(r){return r.json();}).then(function(d){
            if(!d.success||!d.profile){console.warn('Profile load failed',d);return;}
            var p=d.profile;
            var fn=document.getElementById('profile_full_name'),em=document.getElementById('profile_email'),mb=document.getElementById('profile_mobile');
            if(fn)fn.value=p.full_name||'';
            if(em)em.value=p.email||'';
            if(mb)mb.value=p.mobile||'';
            if(p.preferences){
                var ar=document.getElementById('pref_auto_refresh'),nt=document.getElementById('pref_notifications'),sd=document.getElementById('pref_sound');
                if(ar)ar.checked=p.preferences.auto_refresh!==false;
                if(nt)nt.checked=p.preferences.notifications!==false;
                if(sd)sd.checked=!!p.preferences.sound_alerts;
                var theme=p.preferences.theme||'theme-natural-green';
                document.querySelectorAll('.theme-option-card').forEach(function(c){c.classList.toggle('active',c.dataset.theme===theme);});
            }
            if(p.avatar){
                var u=avatarUrl+encodeURIComponent(p.avatar);
                var disp=document.getElementById('displayAvatar');
                if(disp)disp.src=u;
                var rail=document.querySelector('#railProfileBtn img');
                if(rail)rail.src=u;
            }
            var st=document.getElementById('statActions');
            if(st&&p.action_count!==void 0)st.textContent=p.action_count;
            var sl=document.getElementById('statLogins');
            if(sl&&p.login_count!==void 0)sl.textContent=p.login_count;
            var l24=document.getElementById('statLogin24h');
            if(l24&&p.login_24h!==void 0)l24.textContent=p.login_24h;
            var sa=document.getElementById('statAdActions');
            if(sa&&p.ad_action_count!==void 0)sa.textContent=p.ad_action_count;
            var sw=document.getElementById('statWebActions');
            if(sw&&p.web_action_count!==void 0)sw.textContent=p.web_action_count;
            if(p.ad_breakdown){
                var bd=document.getElementById('statAdBreakdown');
                if(bd){
                    var keys=Object.keys(p.ad_breakdown);
                    if(keys.length){
                        bd.innerHTML=keys.map(function(k){return '<span style="font-size:0.68rem;color:var(--text-muted);">'+k+': <strong style="color:var(--primary-color);">'+p.ad_breakdown[k]+'</strong></span>';}).join('');
                        document.getElementById('statDetails').style.display='block';
                    }
                }
            }
        }).catch(function(e){console.error('Profile fetch error:',e);});
    }
    setTimeout(loadProfile, 0);

    function loadActivity(){
        fetch(apiUrl,{method:'POST',body:JSON.stringify({action:'get_activity',limit:8})}).then(function(r){return r.json();}).then(function(d){
            var ct=document.getElementById('profileActivityList');
            if(!ct)return;
            if(!d.success||!d.activity||!d.activity.length){
                ct.innerHTML='<div class="activity-empty text-center py-2">No recent activity.</div>';return;
            }
            var h='';var colors={success:'#22c55e',error:'#ef4444',warning:'#f59e0b',info:'#3b82f6'};
            for(var i=0;i<d.activity.length;i++){
                var a=d.activity[i];var c=colors[a.status]||colors.info;
                h+='<div class="activity-item"><span class="activity-dot" style="background:'+c+';"></span><span class="flex-grow-1">'+escapeHtml(a.action||'Action')+'</span><span class="text-muted font-tech" style="font-size:0.7rem;flex-shrink:0;">'+(a.timestamp||'')+'</span></div>';
            }
            ct.innerHTML=h;
        });
    }
    setTimeout(loadActivity,500);

    var notifUrl = baseUrl + '/notification.php';
    function saveNotifPrefs(){
        var st=document.getElementById('profileShowToasts'),cats={};
        document.querySelectorAll('.notif-cat-cb').forEach(function(cb){cats[cb.value]=cb.checked;});
        fetch(notifUrl+'?action=save_preferences',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({show_toasts:st?st.checked:true,categories:cats})}).then(function(r){return r.json();}).then(function(d){
            if(d.success){var sd=document.getElementById('pref_sound');if(sd&&sd.checked)playNotificationSound();}
        });
    }
    function loadNotifPrefs(){
        fetch(notifUrl+'?action=get_preferences').then(function(r){return r.json();}).then(function(d){
            var st=document.getElementById('profileShowToasts');
            if(d&&d.preferences){
                if(st)st.checked=d.preferences.show_toasts!==false;
                var ct=document.getElementById('profileCategoryPrefs');
                if(ct&&d.preferences.categories){
                    var h='';
                    for(var key in d.preferences.categories){
                        var checked=d.preferences.categories[key]!==false?'checked':'';
                        h+='<label class="notif-cat-pill"><input class="notif-cat-cb" type="checkbox" value="'+key+'" '+checked+'><span class="notif-cat-label">'+key.charAt(0).toUpperCase()+key.slice(1)+'</span></label>';
                    }
                    ct.innerHTML=h;
                    ct.querySelectorAll('.notif-cat-cb').forEach(function(cb){cb.onchange=saveNotifPrefs;});
                }
            }
        });
    }
    loadNotifPrefs();

    function escapeHtml(t){if(!t)return'';return String(t).replace(/[&<>"]/g,function(m){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[m]||m;});}

    // Sound playback
    var _audioCtx=null;
    function playNotificationSound(){
        try{
            if(!_audioCtx)_audioCtx=new(window.AudioContext||window.webkitAudioContext)();
            var o=_audioCtx.createOscillator(),g=_audioCtx.createGain();
            o.connect(g);g.connect(_audioCtx.destination);
            o.frequency.setValueAtTime(880,_audioCtx.currentTime);
            o.frequency.setValueAtTime(1320,_audioCtx.currentTime+0.08);
            o.type='sine';g.gain.setValueAtTime(0.25,_audioCtx.currentTime);
            g.gain.exponentialRampToValueAtTime(0.001,_audioCtx.currentTime+0.25);
            o.start();o.stop(_audioCtx.currentTime+0.25);
        }catch(e){}
    }
    window.playNotificationSound = playNotificationSound;
    window._soundAlertsEnabled = function(){var el=document.getElementById('pref_sound');return el?el.checked:false;};
    var stb=document.getElementById('profileSoundTestBtn');
    if(stb)stb.onclick=function(){playNotificationSound();};

    // Avatar upload
    var trig=document.getElementById('triggerAvatarUpload'),inp=document.getElementById('avatarInput');
    if(trig&&inp){trig.onclick=function(){inp.click();};inp.onchange=function(){
        if(!this.files||!this.files[0])return;
        var f=this.files[0];
        if(f.size>1048576){showResult('Error','Image must be under 1MB.',false);return;}
        var fd=new FormData();fd.append('avatar',f);
        showResult('Avatar','Uploading...',true);
        fetch(apiUrl,{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){
            if(d.success){
                var u=avatarUrl+encodeURIComponent(d.avatar);
                var disp=document.getElementById('displayAvatar');
                if(disp)disp.src=u;
                var rail=document.querySelector('#railProfileBtn img');
                if(rail)rail.src=u;
                showResult('Avatar','Profile picture updated.',true);
            }else{showResult('Error',d.message,false);}
        }).catch(function(e){showResult('Error','Upload failed: '+e.message,false);});
    };}

    // Update details
    var df=document.getElementById('updateProfileDetailsForm');
    if(df){df.onsubmit=function(e){e.preventDefault();
        showResult('Profile','Saving...',true);
        fetch(apiUrl,{method:'POST',body:JSON.stringify({action:'update_details',full_name:document.getElementById('profile_full_name').value,email:document.getElementById('profile_email').value,mobile:document.getElementById('profile_mobile').value})}).then(function(r){return r.json();}).then(function(d){showResult('Profile',d.message,!!d.success);});
    };}

    function applyTheme(t){
        document.body.className=document.body.className.replace(/theme-[\w-]+/g,'').trim();
        document.body.classList.add(t);
        document.cookie='selectedTheme='+t+';path=/;max-age=31536000';
        document.querySelectorAll('.theme-option-card').forEach(function(c){c.classList.toggle('active',c.dataset.theme===t);});
    }
    document.querySelectorAll('.theme-option-card').forEach(function(card){
        card.onclick=function(){
            applyTheme(this.dataset.theme);
            savePrefs();
        };
    });


    function savePrefs(){
        var theme=Array.from(document.body.classList).find(function(c){return c.startsWith('theme-');})||'theme-natural-green';
        var ar=document.getElementById('pref_auto_refresh'),nt=document.getElementById('pref_notifications'),sd=document.getElementById('pref_sound');
        fetch(apiUrl,{method:'POST',body:JSON.stringify({action:'update_preferences',theme:theme,auto_refresh:ar?ar.checked:true,notifications:nt?nt.checked:true,sound_alerts:sd?sd.checked:false})}).then(function(r){return r.json();}).then(function(d){
            if(!d.success)console.warn('Pref save failed',d);
        }).catch(function(e){console.error('Pref save error',e);});
    }
    document.querySelectorAll('.pref-toggle').forEach(function(t){t.onchange=savePrefs;});
    var pst=document.getElementById('profileShowToasts');
    if(pst)pst.onchange=function(){if(typeof saveNotifPrefs==='function')saveNotifPrefs();};
    var sd=document.getElementById('pref_sound');
    if(sd)sd.addEventListener('change',function(){if(this.checked)playNotificationSound();});

    // Eye toggle
    document.querySelectorAll('.password-toggle-btn').forEach(function(btn){
        btn.onclick=function(){
            var inp=document.getElementById(this.dataset.target);
            if(!inp)return;
            var is=inp.type==='password';
            inp.type=is?'text':'password';
            this.innerHTML=is?'<i class="fas fa-eye-slash"></i>':'<i class="fas fa-eye"></i>';
        };
    });

    // Session timer
    var st=document.getElementById('profileSessionTime');
    if(st){
        var start=Date.now();
        function updateSess(){
            var s=Math.floor((Date.now()-start)/1000);
            var m=Math.floor(s/60);s=s%60;
            st.textContent=(m<10?'0':'')+m+':'+(s<10?'0':'')+s;
        }
        updateSess();setInterval(updateSess,1000);
    }

    if(typeof window.initChangePassword==='function')window.initChangePassword();
};
window._profileInitialized = false;
window.initProfileHub();
</script>