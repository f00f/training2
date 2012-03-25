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
