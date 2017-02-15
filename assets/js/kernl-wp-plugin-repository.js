jQuery(document).on('click', '.install-now ', function(e) {
	e.preventDefault();
	var button = jQuery(this);
	var file_url = jQuery(this).data('slug');
	var nonce = jQuery(this).data('nonce');
	console.log(file_url);

	jQuery.ajax({
		url: '/wp-admin/admin-ajax.php',
		method: 'POST',
		data: {
			'action': 'kernl_install_plugin',
			'security': nonce,
			'slug': file_url
		},
		beforeSend: function(response) {
			jQuery(button).text(php.installing).addClass('updating-message').css('cursor', 'wait').css('pointer-events', 'none');
		},
		success: function(response) {
			var response = jQuery.parseJSON( response );
			var activate_button = '<a class="button activate-now button-primary" data-folder="'+response.plugin_folder+'" data-file="'+response.main_file+'">'+php.activate+'</a>';

			jQuery(activate_button).insertAfter(button);
			jQuery(button).remove();
		}
	});
});





jQuery(document).on('click', '.activate-now', function(e) {
	e.preventDefault();
	var button = jQuery(this);
	var plugin_folder = jQuery(this).data('folder');
	var main_file = jQuery(this).data('file');

	jQuery.ajax({
		url: '/wp-admin/admin-ajax.php',
		method: 'POST',
		data: {
			'action': 'kernl_activate_plugin',
			'plugin_folder': plugin_folder,
			'main_file': main_file
		},
		beforeSend: function(response) {
			jQuery(button).text(php.activating).css('cursor', 'wait').css('pointer-events', 'none');
		},
		success: function(response) {
			jQuery(button).addClass('disabled').text(php.activated);
		}
	});
});





jQuery(document).on('ready', function(e) {
	var options = {
		valueNames: [ 'plugin-name' ]
	};

	var pluginList = new List('plugin-list', options);
	console.log(pluginList);
});