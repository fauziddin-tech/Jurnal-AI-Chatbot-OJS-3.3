<?php
import('lib.pkp.classes.plugins.GenericPlugin');

class JurnalChatbotPlugin extends GenericPlugin {

	function register($category, $path, $mainContextId = null) {
		$success = parent::register($category, $path, $mainContextId);
		// Pola wajib dari customHeader
		if (!Config::getVar('general', 'installed') || defined('RUNNING_UPGRADE')) return true;
		if ($success && $this->getEnabled()) {
			HookRegistry::register('Templates::Common::Footer::PageFooter', array($this, 'insertChatbot'));
			HookRegistry::register('LoadHandler', array($this, 'setPageHandler'));
		}
		return $success;
	}

	function getDisplayName() {
		return __('plugins.generic.jurnalChatbot.displayName');
	}

	function getDescription() {
		return __('plugins.generic.jurnalChatbot.description');
	}

	function isSitePlugin() {
		return !(Application::get()->getRequest()->getContext());
	}

	function _s($key, $default = '', $contextId = null) {
		if ($contextId === null) $contextId = $this->getCurrentContextId();
		$v = $this->getSetting($contextId, $key);
		return ($v !== null && $v !== '') ? $v : $default;
	}

	function buildPrompt($contextId = null) {
		$name   = $this->_s('journalName',         'Jurnal Ilmiah', $contextId);
		$email  = $this->_s('journalEmail',         '', $contextId);
		$url    = $this->_s('journalUrl',           '', $contextId);
		$issn   = $this->_s('journalIssn',          '', $contextId);
		$pub    = $this->_s('journalPublisher',     '', $contextId);
		$accred = $this->_s('journalAccreditation', '', $contextId);
		$index  = $this->_s('journalIndex',         '', $contextId);
		$freq   = $this->_s('pubFrequency',         '', $contextId);
		$apcR   = $this->_s('apcRegular',           '', $contextId);
		$apcP   = $this->_s('apcPriority',          '', $contextId);
		$apcPol = $this->_s('apcPolicy',            '', $contextId);
		$fmt    = $this->_s('subFormat',            '', $contextId);
		$tmpl   = $this->_s('subTemplate',          '', $contextId);
		$plag   = $this->_s('subPlagiarism',        '30%', $contextId);
		$cite   = $this->_s('subCitation',          'APA', $contextId);
		$scope  = $this->_s('journalScope',         '', $contextId);
		$lSub   = $this->_s('linkSubmit',           $url, $contextId);
		$lReg   = $this->_s('linkRegister',         $url, $contextId);
		$lGuide = $this->_s('linkGuidelines',       $url, $contextId);
		$extra  = $this->_s('extraInfo',            '', $contextId);

		$p  = 'You are an official AI assistant for ' . $name . '.' . "\n\n";
		$p .= 'JOURNAL:\n- Name: ' . $name . '\n- Website: ' . $url . '\n- Email: ' . $email;
		if ($pub)    $p .= '\n- Publisher: ' . $pub;
		if ($issn)   $p .= '\n- ISSN: ' . $issn;
		if ($accred) $p .= '\n- Accreditation: ' . $accred;
		if ($index)  $p .= '\n- Index: ' . $index;
		$p .= '\n- Review: Double-blind\n- Open access: Yes\n\n';
		if ($freq)   $p .= 'SCHEDULE:\n- ' . $freq . '\n\n';
		$p .= 'SUBMISSION:\n- Submit: ' . $lSub . '\n- Register: ' . $lReg;
		$p .= '\n- Plagiarism max: ' . $plag . '\n- Citation: ' . $cite;
		if ($fmt) { foreach (explode("\n", trim($fmt)) as $l) { $l=trim($l); if($l) $p.='\n- '.$l; } }
		if ($tmpl) $p .= '\n- Template DOWNLOAD: ' . $tmpl;
		$p .= '\n\nAPC:\n';
		if ($apcR)   $p .= '- Regular: ' . $apcR . '\n';
		if ($apcP)   $p .= '- Priority: ' . $apcP . '\n';
		if ($apcPol) { foreach (explode("\n", trim($apcPol)) as $l) { $l=trim($l); if($l) $p.='- '.$l.'\n'; } }
		if (!$apcR && !$apcP) $p .= '- Contact ' . $email . ' for APC info\n';
		$p .= '\n';
		if ($scope) {
			$p .= 'SCOPE:\n';
			foreach (explode("\n", trim($scope)) as $l) { $l=trim($l); if($l) $p.='- '.$l.'\n'; }
			$p .= '\n';
		}
		$p .= 'LINKS:\n- Homepage: ' . $url;
		if ($lSub)   $p .= '\n- Submit: ' . $lSub;
		if ($lReg)   $p .= '\n- Register: ' . $lReg;
		if ($lGuide) $p .= '\n- Guidelines: ' . $lGuide;
		$p .= '\n\n';
		if ($extra) {
			$p .= 'ADDITIONAL:\n';
			foreach (explode("\n", trim($extra)) as $l) { $l=trim($l); if($l) $p.='- '.$l.'\n'; }
			$p .= '\n';
		}
		$p .= 'RULES:\n1. Reply in same language as user\n2. Concise max 180 words\n';
		$p .= '3. Use bullet points for steps\n4. Format links as Markdown: [label](https://example.com)\n';
		if ($tmpl) $p .= '5. For template questions, include [Download Template](' . $tmpl . ')\n';
		$p .= '6. Payment/waiver/LOA -> ' . $email . '\n7. Never fabricate data';
		return $p;
	}

	function setPageHandler($hookName, $params) {
		$page = $params[0];
		if ($page !== 'jurnalChatbot') return false;
		$this->import('JurnalChatbotHandler');
		if (!defined('HANDLER_CLASS')) define('HANDLER_CLASS', 'JurnalChatbotHandler');
		JurnalChatbotHandler::$plugin = $this;
		return true;
	}

	function insertChatbot($hookName, $params) {
		$output =& $params[2];
		$request = Application::get()->getRequest();
		$context = $request->getContext();
		// Pastikan hanya inject di frontend, bukan backend
		if (!$context) return false;
		$output .= $this->_buildHtml();
		return false;
	}

	function _buildHtml() {
		$rawColor = $this->_s('themeColor', '#1a4a2e');
		$color   = preg_match('/^#[0-9a-fA-F]{6}$/', $rawColor) ? $rawColor : '#1a4a2e';
		$themeMode = $this->_s('themeMode', 'auto') === 'manual' ? 'manual' : 'auto';
		$botName = htmlspecialchars($this->_s('chatbotName', 'Asisten Jurnal'), ENT_QUOTES, 'UTF-8');
		$jName   = htmlspecialchars($this->_s('journalName', 'Jurnal'), ENT_QUOTES, 'UTF-8');
		$email   = htmlspecialchars($this->_s('journalEmail', ''), ENT_QUOTES, 'UTF-8');
		$request = Application::get()->getRequest();
		$context = $request->getContext();
		$proxy   = $request->getDispatcher()->url(
			$request,
			ROUTE_PAGE,
			$context->getPath(),
			'jurnalChatbot',
			'api'
		);
		$accred  = htmlspecialchars($this->_s('journalAccreditation', ''), ENT_QUOTES, 'UTF-8');
		$lSub    = $this->_safeUrl($this->_s('linkSubmit', ''));
		$tmpl    = $this->_safeUrl($this->_s('subTemplate', ''));
		$accredBadge = $accred
			? '<span class="jcb-accred" style="font-size:10px;font-family:sans-serif;padding:2px 7px;border-radius:10px;font-weight:600;background:' . $color . ';color:#fff">' . $accred . '</span>'
			: '';

		$welcome = 'Halo! Selamat datang di <strong>' . $jName . '</strong> &#128075;<br><br>Saya siap membantu:<br>&#8226; Submit naskah<br>&#8226; Cek status naskah<br>&#8226; Template &amp; panduan<br>&#8226; Biaya APC<br>&#8226; Scope jurnal<br><br>Pilih topik atau ketik pertanyaan!';
		if ($lSub) $welcome = 'Halo! Selamat datang di <strong>' . $jName . '</strong> &#128075;<br><br>Saya siap membantu:<br>&#8226; <a href="' . $lSub . '" target="_blank" rel="noopener noreferrer" class="jcb-link">Submit naskah</a><br>&#8226; Cek status naskah<br>&#8226; Template &amp; panduan<br>&#8226; Biaya APC<br>&#8226; Scope jurnal<br><br>Pilih topik atau ketik pertanyaan!';

		$c = $color;
		$html  = "\n<!-- Jurnal AI Chatbot Plugin v1.3 -->\n";
		$html .= '<button id="jcb-trigger" onclick="jcbToggle()" aria-label="Buka asisten" style="position:fixed;bottom:24px;right:24px;width:56px;height:56px;border-radius:50%;background:' . $c . ';border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 16px rgba(0,0,0,.25);z-index:9998">';
		$html .= '<svg width="26" height="26" fill="#fff" viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-2 10H6v-2h12v2zm0-3H6V7h12v2z"/></svg>';
		$html .= '<div id="jcb-notif" style="position:absolute;top:4px;right:4px;width:11px;height:11px;background:#4ade80;border-radius:50%;border:2px solid ' . $c . '"></div></button>' . "\n";
		$html .= '<div id="jcb-panel" role="dialog" style="position:fixed;bottom:90px;right:24px;width:360px;max-width:calc(100vw - 32px);height:560px;max-height:calc(100vh - 110px);background:#fff;border-radius:16px;border:1px solid #d4e8dc;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 8px 32px rgba(0,0,0,.14);z-index:9999;transform:scale(.92) translateY(16px);opacity:0;pointer-events:none;transition:transform .22s cubic-bezier(.34,1.56,.64,1),opacity .18s ease">' . "\n";
		$html .= '<div id="jcb-header" style="background:' . $c . ';padding:12px 14px;display:flex;align-items:center;gap:10px;flex-shrink:0">';
		$html .= '<div style="width:36px;height:36px;border-radius:50%;background:rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;flex-shrink:0"><svg width="18" height="18" fill="rgba(255,255,255,.85)" viewBox="0 0 24 24"><path d="M12 3L1 9l11 6 9-4.91V17h2V9L12 3zM5 13.18v4L12 21l7-3.82v-4L12 17l-7-3.82z"/></svg></div>';
		$html .= '<div><div class="jcb-on" style="color:#fff;font-size:13px;font-weight:600;font-family:sans-serif">' . $botName . '</div><div class="jcb-on-muted" style="color:rgba(255,255,255,.7);font-size:10px;font-family:sans-serif">' . $jName . '</div></div>';
		$html .= '<div style="width:7px;height:7px;background:#4ade80;border-radius:50%;margin-left:auto;flex-shrink:0"></div>';
		$html .= '<button class="jcb-on-muted" onclick="jcbToggle()" style="margin-left:6px;background:none;border:none;cursor:pointer;color:rgba(255,255,255,.7);font-size:20px;line-height:1;flex-shrink:0;padding:2px">&#x2715;</button></div>' . "\n";
		$html .= '<div style="display:flex;align-items:center;gap:5px;padding:5px 12px;background:#f0f8f3;border-bottom:1px solid #d4e8dc;flex-wrap:wrap;flex-shrink:0">' . $accredBadge;
		$html .= '<span style="font-size:10px;font-family:sans-serif;padding:2px 7px;border-radius:10px;font-weight:600;background:#e4dff8;color:#3a2a80">Blind Review</span>';
		$html .= '<button id="jcb-bid" onclick="jcbLang(\'id\')" style="font-size:10px;font-family:sans-serif;padding:2px 7px;border-radius:10px;border:1px solid ' . $c . ';background:' . $c . ';color:#fff;cursor:pointer">ID</button>';
		$html .= '<button id="jcb-ben" onclick="jcbLang(\'en\')" style="font-size:10px;font-family:sans-serif;padding:2px 7px;border-radius:10px;border:1px solid #ccc;background:transparent;color:#555;cursor:pointer">EN</button></div>' . "\n";
		$html .= '<div id="jcb-msgs" style="flex:1;overflow-y:auto;padding:12px;display:flex;flex-direction:column;gap:8px;background:#f5f9f6"></div>' . "\n";
		$html .= '<div id="jcb-sf" style="display:none;padding:10px 12px;border-top:1px solid #d4e8dc;background:#f0f8f3;flex-shrink:0">';
		$html .= '<div class="jcb-primary-text" style="font-size:11px;color:' . $c . ';font-weight:600;font-family:sans-serif;margin-bottom:7px">&#128269; Cek Status Naskah</div>';
		$html .= '<input id="jcb-sid" type="number" placeholder="ID Submission" style="width:100%;border:1px solid #cde0d5;border-radius:8px;padding:6px 10px;font-size:12px;font-family:sans-serif;outline:none;margin-bottom:6px;display:block"/>';
		$html .= '<input id="jcb-sem" type="email" placeholder="Email penulis terdaftar" style="width:100%;border:1px solid #cde0d5;border-radius:8px;padding:6px 10px;font-size:12px;font-family:sans-serif;outline:none;margin-bottom:7px;display:block"/>';
		$html .= '<div style="display:flex;gap:6px"><button id="jcb-status-submit" onclick="jcbCheckStatus()" style="flex:1;background:' . $c . ';color:#fff;border:none;border-radius:8px;padding:7px;font-size:12px;font-family:sans-serif;cursor:pointer;font-weight:600">Cek Status</button>';
		$html .= '<button onclick="jcbCloseSF()" style="background:transparent;color:#888;border:1px solid #ccc;border-radius:8px;padding:7px 12px;font-size:12px;font-family:sans-serif;cursor:pointer">Batal</button></div></div>' . "\n";
		$html .= '<div id="jcb-cw" style="padding:6px 11px;border-top:1px solid #d4e8dc;background:#fff;flex-shrink:0"><div style="font-size:10px;color:#888;margin-bottom:5px;font-family:sans-serif">Pertanyaan umum:</div><div id="jcb-chips" style="display:flex;flex-wrap:wrap;gap:4px"></div></div>' . "\n";
		$html .= '<div style="padding:8px 10px;border-top:1px solid #d4e8dc;display:flex;gap:7px;align-items:flex-end;background:#fff;flex-shrink:0">';
		$html .= '<textarea id="jcb-inp" placeholder="Ketik pertanyaan Anda..." rows="1" style="flex:1;border:1px solid #cde0d5;border-radius:16px;padding:7px 12px;font-size:12.5px;font-family:sans-serif;background:#f5f9f6;color:#1a1a1a;outline:none;resize:none;max-height:70px;line-height:1.4" onkeydown="jcbKey(event)" oninput="jcbResize(this)"></textarea>';
		$html .= '<button id="jcb-send" onclick="jcbSend()" style="width:32px;height:32px;border-radius:50%;background:' . $c . ';border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0"><svg width="14" height="14" fill="#fff" viewBox="0 0 24 24"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg></button></div>' . "\n";
		$html .= '<div style="text-align:center;padding:4px;font-size:9.5px;color:#aaa;font-family:sans-serif;background:#fff;border-top:1px solid #f0f0f0;flex-shrink:0">Powered by AI &middot; ' . $email . '</div></div>' . "\n";
		$html .= '<style>.jcb-chip{font-size:11px;font-family:sans-serif;padding:3px 9px;border-radius:14px;border:1px solid #cde0d5;background:#f0f8f3;color:#1a4a2e;cursor:pointer;transition:all .12s}.jcb-chip:hover,.jcb-hl{background:' . $c . ';color:#fff;border-color:' . $c . '}.jcb-hl{font-size:11px;font-family:sans-serif;padding:3px 9px;border-radius:14px;cursor:pointer}.jcb-b{padding:8px 12px;border-radius:14px;border-bottom-left-radius:3px;font-size:12.5px;line-height:1.6;font-family:sans-serif;background:#fff;border:1px solid #ddeee4;color:#1a1a1a}.jcb-u{padding:8px 12px;border-radius:14px;border-bottom-right-radius:3px;font-size:12.5px;line-height:1.6;font-family:sans-serif;background:' . $c . ';color:#fff}.jcb-st{padding:10px 13px;border-radius:14px;border-bottom-left-radius:3px;font-size:12px;line-height:1.8;font-family:sans-serif;background:#f0f8f3;border:1px solid #a8d8b4;color:#1a1a1a}.jcb-link{color:' . $c . ';text-decoration:underline;font-weight:500}.jcb-typing{display:flex;align-items:center;gap:4px;padding:8px 12px;background:#fff;border:1px solid #ddeee4;border-radius:14px;border-bottom-left-radius:3px;width:fit-content}.jcb-typing span{width:5px;height:5px;background:#7db892;border-radius:50%;animation:jcbbl 1.2s infinite}.jcb-typing span:nth-child(2){animation-delay:.2s}.jcb-typing span:nth-child(3){animation-delay:.4s}@keyframes jcbbl{0%,80%,100%{opacity:.3}40%{opacity:1}}#jcb-trigger,#jcb-header,#jcb-send,#jcb-status-submit,.jcb-accred,.jcb-u,.jcb-hl,.jcb-chip:hover{background:var(--jcb-primary)!important;color:var(--jcb-on)!important}.jcb-chip:hover,.jcb-hl{border-color:var(--jcb-primary)!important}.jcb-link,.jcb-primary-text{color:var(--jcb-primary)!important}.jcb-chip{background:var(--jcb-soft)!important;border-color:var(--jcb-border)!important;color:var(--jcb-primary)!important}.jcb-st,#jcb-sf{background:var(--jcb-soft)!important;border-color:var(--jcb-border)!important}#jcb-panel{border-color:var(--jcb-border)!important}.jcb-on{color:var(--jcb-on)!important}.jcb-on-muted{color:var(--jcb-on-muted)!important}#jcb-trigger svg,#jcb-send svg,#jcb-header svg{fill:var(--jcb-on)!important}#jcb-notif{border-color:var(--jcb-primary)!important}.jcb-typing span{background:var(--jcb-primary)!important}@media(max-width:400px){#jcb-panel{right:8px;bottom:80px;width:calc(100vw - 16px)}#jcb-trigger{right:12px;bottom:16px}}</style>' . "\n";
		$html .= '<script>' . "\n";
		$config = array(
			'proxy' => $proxy,
			'color' => $c,
			'themeMode' => $themeMode,
			'on' => '#ffffff',
			'email' => html_entity_decode($email, ENT_QUOTES, 'UTF-8'),
			'tmpl' => html_entity_decode($tmpl, ENT_QUOTES, 'UTF-8'),
			'lang' => 'id',
			'hist' => array(),
			'busy' => false,
			'open' => false,
			'w' => $welcome,
		);
		$html .= 'var JCB=' . json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';' . "\n";
		$html .= 'var JT={id:[{l:"Cara submit",q:"Bagaimana cara submit naskah?",s:0},{l:"&#128269; Cek Status",q:"",s:1},{l:"Template",q:"Bagaimana format dan template naskah?",s:0},{l:"Biaya APC",q:"Berapa biaya publikasi (APC)?",s:0},{l:"Alur review",q:"Bagaimana alur proses review dan publikasi?",s:0},{l:"Waiver",q:"Apakah ada keringanan biaya?",s:0},{l:"Scope",q:"Apa saja topik yang diterima?",s:0},{l:"Jadwal terbit",q:"Kapan jadwal penerbitan?",s:0}],en:[{l:"How to submit",q:"How do I submit a manuscript?",s:0},{l:"&#128269; Check Status",q:"",s:1},{l:"Template",q:"What is the manuscript format?",s:0},{l:"APC fee",q:"What is the APC?",s:0},{l:"Review process",q:"What is the review workflow?",s:0},{l:"Fee waiver",q:"Is there a fee waiver?",s:0},{l:"Scope",q:"What topics does this journal accept?",s:0},{l:"Schedule",q:"When does this journal publish?",s:0}]};' . "\n";
		$html .= 'function jcbTime(){return new Date().toLocaleTimeString("id-ID",{hour:"2-digit",minute:"2-digit"});}' . "\n";
		$html .= 'function jcbRgb(v){if(!v)return null;var h=v.trim().match(/^#([0-9a-f]{6})$/i);if(h){return [parseInt(h[1].slice(0,2),16),parseInt(h[1].slice(2,4),16),parseInt(h[1].slice(4,6),16)];}var r=v.match(/rgba?\\(\\s*(\\d+)[, ]+\\s*(\\d+)[, ]+\\s*(\\d+)/i);return r?[+r[1],+r[2],+r[3]]:null;}' . "\n";
		$html .= 'function jcbHex(r){return "#"+r.map(function(x){return Math.max(0,Math.min(255,Math.round(x))).toString(16).padStart(2,"0");}).join("");}' . "\n";
		$html .= 'function jcbMix(r,n){return jcbHex(r.map(function(x){return x+(255-x)*n;}));}' . "\n";
		$html .= 'function jcbUsable(r){if(!r)return false;var max=Math.max.apply(null,r),min=Math.min.apply(null,r),lum=(r[0]*299+r[1]*587+r[2]*114)/1000;return max-min>=24&&lum>28&&lum<232;}' . "\n";
		$html .= 'function jcbThemeColor(){var fallback=jcbRgb(JCB.color);if(JCB.themeMode!=="auto")return fallback;var roots=[document.documentElement,document.body],vars=["--primary","--primary-color","--color-primary","--theme-primary-color","--pkp-primary","--accent-color"];for(var a=0;a<roots.length;a++){var cs=getComputedStyle(roots[a]);for(var b=0;b<vars.length;b++){var vr=jcbRgb(cs.getPropertyValue(vars[b]));if(jcbUsable(vr))return vr;}}var sels=[".pkp_button_primary",".cmp_button",".pkp_structure_main a",".page a",".pkp_navigation_primary a",".pkp_site_nav_menu",".pkp_structure_head","a"],best=null,score=-1;for(var i=0;i<sels.length;i++){var els=document.querySelectorAll(sels[i]);for(var j=0;j<Math.min(els.length,20);j++){if(els[j].closest("#jcb-panel")||els[j].closest("#jcb-trigger"))continue;var st=getComputedStyle(els[j]),vals=[st.backgroundColor,st.borderTopColor,st.color];for(var k=0;k<vals.length;k++){var rr=jcbRgb(vals[k]);if(!jcbUsable(rr))continue;var chroma=Math.max.apply(null,rr)-Math.min.apply(null,rr),s=chroma+(sels.length-i)*3;if(s>score){best=rr;score=s;}}}}return best||fallback;}' . "\n";
		$html .= 'function jcbApplyTheme(){var r=jcbThemeColor()||[26,74,46],hex=jcbHex(r),lum=(r[0]*299+r[1]*587+r[2]*114)/1000,on=lum>160?"#182028":"#ffffff";JCB.color=hex;JCB.on=on;var d=document.documentElement.style;d.setProperty("--jcb-primary",hex);d.setProperty("--jcb-on",on);d.setProperty("--jcb-on-muted",on==="#ffffff"?"rgba(255,255,255,.72)":"rgba(24,32,40,.72)");d.setProperty("--jcb-soft",jcbMix(r,.91));d.setProperty("--jcb-border",jcbMix(r,.72));}' . "\n";
		$html .= 'function jcbToggle(){JCB.open=!JCB.open;var p=document.getElementById("jcb-panel");if(JCB.open){p.style.cssText+="transform:scale(1) translateY(0);opacity:1;pointer-events:all";document.getElementById("jcb-notif").style.display="none";if(!document.getElementById("jcb-w")){jcbInit();}setTimeout(function(){document.getElementById("jcb-inp").focus();},250);}else{p.style.cssText+="transform:scale(.92) translateY(16px);opacity:0;pointer-events:none";jcbCloseSF();}}' . "\n";
		$html .= 'function jcbInit(){var box=document.getElementById("jcb-msgs");var d=document.createElement("div");d.id="jcb-w";d.style.cssText="max-width:92%;display:flex;flex-direction:column;gap:3px;align-self:flex-start";var b=document.createElement("div");b.className="jcb-b";b.innerHTML=JCB.w;var t=document.createElement("span");t.style.cssText="font-size:10px;color:#888;font-family:sans-serif";t.textContent=jcbTime();d.appendChild(b);d.appendChild(t);box.appendChild(d);}' . "\n";
		$html .= 'function jcbLang(l){JCB.lang=l;var bid=document.getElementById("jcb-bid"),ben=document.getElementById("jcb-ben");bid.style.background=l==="id"?JCB.color:"transparent";bid.style.color=l==="id"?JCB.on:"#555";bid.style.borderColor=l==="id"?JCB.color:"#ccc";ben.style.background=l==="en"?JCB.color:"transparent";ben.style.color=l==="en"?JCB.on:"#555";ben.style.borderColor=l==="en"?JCB.color:"#ccc";jcbChips();document.getElementById("jcb-inp").placeholder=l==="id"?"Ketik pertanyaan Anda...":"Type your question...";}' . "\n";
		$html .= 'function jcbChips(){var c=document.getElementById("jcb-chips");c.innerHTML="";JT[JCB.lang].forEach(function(t){var b=document.createElement("button");b.className=t.s?"jcb-hl":"jcb-chip";b.innerHTML=t.l;if(t.s){b.onclick=function(){jcbOpenSF();};}else{b.onclick=function(){document.getElementById("jcb-inp").value=t.q;jcbSend();};}c.appendChild(b);});}' . "\n";
		$html .= 'function jcbOpenSF(){document.getElementById("jcb-sf").style.display="block";document.getElementById("jcb-cw").style.display="none";document.getElementById("jcb-sid").focus();}' . "\n";
		$html .= 'function jcbCloseSF(){document.getElementById("jcb-sf").style.display="none";document.getElementById("jcb-cw").style.display="block";document.getElementById("jcb-sid").value="";document.getElementById("jcb-sem").value="";}' . "\n";
		$html .= 'async function jcbCheckStatus(){var sid=document.getElementById("jcb-sid").value.trim();var em=document.getElementById("jcb-sem").value.trim();var isId=JCB.lang==="id";if(!sid||!em){alert(isId?"Mohon isi ID dan email.":"Please fill ID and email.");return;}jcbCloseSF();var box=document.getElementById("jcb-msgs");var du=document.createElement("div");du.style.cssText="max-width:88%;display:flex;flex-direction:column;gap:3px;align-self:flex-end";var bu=document.createElement("div");bu.className="jcb-u";bu.textContent=(isId?"Cek status ID ":"Check status ID ")+sid;var tu=document.createElement("span");tu.style.cssText="font-size:10px;color:#888;font-family:sans-serif;text-align:right";tu.textContent=jcbTime();du.appendChild(bu);du.appendChild(tu);box.appendChild(du);var dl=document.createElement("div");dl.id="jcb-sl";dl.style.cssText="max-width:88%;align-self:flex-start";dl.innerHTML=\'<div class="jcb-typing"><span></span><span></span><span></span></div>\';box.appendChild(dl);box.scrollTop=box.scrollHeight;try{var r=await fetch(JCB.proxy,{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({action:"status",submission_id:parseInt(sid),email:em})});var d=await r.json();var ld=document.getElementById("jcb-sl");if(ld)ld.remove();var db=document.createElement("div");db.style.cssText="max-width:95%;display:flex;flex-direction:column;gap:3px;align-self:flex-start";var bb=document.createElement("div");bb.className="jcb-st";if(d.error==="not_found"){bb.innerHTML="&#10060; ID <strong>"+jcbEsc(sid)+"</strong> "+(isId?"tidak ditemukan.":"not found.");}else if(d.error==="email_mismatch"){bb.innerHTML="&#10060; "+(isId?"Email tidak cocok ID <strong>"+jcbEsc(sid)+"</strong>.":"Email mismatch for ID <strong>"+jcbEsc(sid)+"</strong>.");}else if(d.error){bb.textContent=isId?"Terjadi kesalahan. Silakan hubungi "+JCB.email:"An error occurred. Contact "+JCB.email;}else{bb.innerHTML="&#128203; <strong>"+(isId?"Status Naskah":"Manuscript Status")+"</strong><br><br>&#128284; <strong>ID:</strong> "+jcbEsc(String(d.id))+"<br>&#128196; <strong>"+(isId?"Judul":"Title")+":</strong> "+jcbEsc(String(d.title))+"<br>&#128100; <strong>"+(isId?"Penulis":"Author")+":</strong> "+jcbEsc(String(d.author))+"<br>&#128197; <strong>"+(isId?"Submit":"Submitted")+":</strong> "+jcbEsc(String(d.dateSubmitted))+"<br>&#128336; <strong>Stage:</strong> "+jcbEsc(String(d.stage))+"<br>&#9989; <strong>Status:</strong> "+jcbEsc(String(d.status))+"<br><br><a href=\'"+jcbEsc(String(d.dashboardUrl))+"\' target=\'_blank\' rel=\'noopener noreferrer\' class=\'jcb-link\'>&#128279; Dashboard OJS</a>";}var tb=document.createElement("span");tb.style.cssText="font-size:10px;color:#888;font-family:sans-serif";tb.textContent=jcbTime();db.appendChild(bb);db.appendChild(tb);box.appendChild(db);box.scrollTop=box.scrollHeight;}catch(e){var ld2=document.getElementById("jcb-sl");if(ld2)ld2.remove();jcbAddMsg("Koneksi gagal. Email <strong>"+jcbEsc(JCB.email)+"</strong>","b");}}' . "\n";
		$html .= 'function jcbAddMsg(html,role){var box=document.getElementById("jcb-msgs");var d=document.createElement("div");d.style.cssText="max-width:88%;display:flex;flex-direction:column;gap:3px;align-self:"+(role==="u"?"flex-end":"flex-start");var b=document.createElement("div");b.className=role==="u"?"jcb-u":"jcb-b";b.innerHTML=html;var t=document.createElement("span");t.style.cssText="font-size:10px;color:#888;font-family:sans-serif"+(role==="u"?";text-align:right":"");t.textContent=jcbTime();d.appendChild(b);d.appendChild(t);box.appendChild(d);box.scrollTop=box.scrollHeight;return b;}' . "\n";
		$html .= 'function jcbEsc(s){return s.replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;").replace(/\'/g,"&#039;");}' . "\n";
		$html .= 'function jcbFmt(s){var o=jcbEsc(s);o=o.replace(/\\*\\*(.*?)\\*\\*/g,"<strong>$1</strong>").replace(/\\[([^\\]]+)\\]\\((https?:\\/\\/[^\\s)]+)\\)/g,\'<a href="$2" target="_blank" rel="noopener noreferrer" class="jcb-link">$1</a>\').replace(/\\n/g,"<br>");if(JCB.tmpl&&(s.toLowerCase().indexOf("template")>=0||s.toLowerCase().indexOf("format naskah")>=0)&&o.indexOf(jcbEsc(JCB.tmpl))<0){var a=document.createElement("a");a.href=JCB.tmpl;if(a.protocol==="http:"||a.protocol==="https:"){o+=\'<br><br><a href="\'+jcbEsc(a.href)+\'" target="_blank" rel="noopener noreferrer" style="display:inline-block;margin-top:4px;padding:6px 14px;background:\'+JCB.color+\';color:\'+JCB.on+\';border-radius:8px;font-size:12px;text-decoration:none;font-weight:600">&#11015; Download Template</a>\';}}return o;}' . "\n";
		$html .= 'function jcbResize(el){el.style.height="auto";el.style.height=Math.min(el.scrollHeight,70)+"px";}' . "\n";
		$html .= 'function jcbKey(e){if(e.key==="Enter"&&!e.shiftKey){e.preventDefault();jcbSend();}}' . "\n";
		$html .= 'async function jcbSend(){if(JCB.busy)return;var inp=document.getElementById("jcb-inp");var txt=inp.value.trim();if(!txt)return;var kw=["cek status","check status","status naskah"];for(var k=0;k<kw.length;k++){if(txt.toLowerCase().indexOf(kw[k])>=0){inp.value="";jcbOpenSF();return;}}inp.value="";inp.style.height="auto";jcbAddMsg(jcbEsc(txt),"u");JCB.hist.push({role:"user",content:txt});JCB.busy=true;document.getElementById("jcb-send").disabled=true;var box=document.getElementById("jcb-msgs");var dtyp=document.createElement("div");dtyp.id="jcb-typ";dtyp.style.cssText="max-width:88%;align-self:flex-start";dtyp.innerHTML=\'<div class="jcb-typing"><span></span><span></span><span></span></div>\';box.appendChild(dtyp);box.scrollTop=box.scrollHeight;try{var res=await fetch(JCB.proxy,{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({action:"chat",messages:JCB.hist})});var data=await res.json();if(!res.ok||!data.text)throw new Error(data.error||("HTTP "+res.status));var t2=document.getElementById("jcb-typ");if(t2)t2.remove();jcbAddMsg(jcbFmt(data.text),"b");JCB.hist.push({role:"assistant",content:data.text});if(JCB.hist.length>12)JCB.hist=JCB.hist.slice(-12);}catch(e){var t3=document.getElementById("jcb-typ");if(t3)t3.remove();jcbAddMsg(JCB.lang==="id"?"Koneksi AI belum tersedia. Periksa API key dan model, atau hubungi <strong>"+jcbEsc(JCB.email)+"</strong>":"AI connection is unavailable. Check the API key and model, or contact <strong>"+jcbEsc(JCB.email)+"</strong>","b");}JCB.busy=false;document.getElementById("jcb-send").disabled=false;}' . "\n";
		$html .= 'jcbApplyTheme();jcbLang("id");' . "\n";
		$html .= '</script>' . "\n";
		$html .= '<!-- End Jurnal AI Chatbot -->' . "\n";
		return $html;
	}

	function _safeUrl($url) {
		$url = trim((string) $url);
		if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) return '';
		$scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
		if ($scheme !== 'http' && $scheme !== 'https') return '';
		return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
	}

	function getActions($request, $verb) {
		$router = $request->getRouter();
		import('lib.pkp.classes.linkAction.request.AjaxModal');
		return array_merge(
			$this->getEnabled() ? array(
				new LinkAction(
					'settings',
					new AjaxModal(
						$router->url($request, null, null, 'manage', null,
							array('verb' => 'settings', 'plugin' => $this->getName(), 'category' => 'generic')),
						$this->getDisplayName()
					),
					__('manager.plugins.settings'),
					null
				),
			) : array(),
			parent::getActions($request, $verb)
		);
	}

	function manage($args, $request) {
		switch ($request->getUserVar('verb')) {
			case 'settings':
				$context = $request->getContext();
				// Wajib — seperti customHeader
				AppLocale::requireComponents(LOCALE_COMPONENT_APP_COMMON, LOCALE_COMPONENT_PKP_MANAGER);
				$templateMgr = TemplateManager::getManager($request);

				$this->import('JurnalChatbotSettingsForm');
				$form = new JurnalChatbotSettingsForm($this, $context ? $context->getId() : CONTEXT_ID_NONE);

				if ($request->getUserVar('save')) {
					$form->readInputData();
					if ($form->validate()) {
						$form->execute();
						return new JSONMessage(true);
					}
				} else {
					$form->initData();
				}
				return new JSONMessage(true, $form->fetch($request));
		}
		return parent::manage($args, $request);
	}
}
