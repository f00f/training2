/*
 * https://github.com/f00f/training2
 * copyright (c) f00f 2012+
 * JavaScript für die Trainings-Seite
 */
function FirstWord(txt) {
	//preg_match('/^(.*?)[^A-Za-z0-9äöüßÄÖÜ]/', $p_text, $matches);
	var pattern = /^(.*?)[^A-Za-z0-9äöüßÄÖÜ]/;
	var m = txt.match(pattern);
	if (null === m) {
		return txt;
	}
	if (m.length > 1) {
		return m[1];
	}
	return undefined;
}
function updatePlayernames() {
	var name = jQuery('#reply-form #player').val();
	var nameDisp = name;
	if ('' == name) {
		nameDisp = 'dich';
	}
	jQuery('.upcoming .playername').each(function(idx, elem) {
		jQuery(elem).html('Für '+nameDisp);
		// TODO: update links (with ``name'')
	});
}
function InstallPlayerHandlers() {
	var players = jQuery('span.player');
	for (var i=0, span=null; i<players.length; i++) {
		span = players[i];
		if (1 != span.nodeType)
			{ continue; }
		span.onclick = PlayerItemOnClickHandler;
	}
}
function PlayerItemOnClickHandler(evt) {
	var obj = (!evt) ? window.event.srcElement : evt.target;
	gComboInput.val(obj.innerHTML);
	// IE Bug: move cursor to the end
	if (gComboInput.createTextRange) {
		var tr = gComboInput.createTextRange();
		tr.collapse(false);
		tr.select();
	}
	gComboInput.focus();
}
