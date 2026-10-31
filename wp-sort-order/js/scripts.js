(function($){

	if ( typeof wpso_obj === 'undefined' ) {
		return;
	}

	var fixHelper = function(e, ui) {
		ui.children().children().each(function() {
			$(this).width($(this).width());
		});
		return ui;
	};

	var post_order = function(action, extra) {
		var data = $.extend({
			action: action,
			order: $('#the-list').sortable('serialize'),
			'wpso_nonce': wpso_obj.nonce
		}, extra || {});
		$.post( wpso_obj.ajaxurl, data );
	};

	// posts
	$('table.posts #the-list, table.pages #the-list').sortable({
		'items': 'tr',
		'axis': 'y',
		'helper': fixHelper,
		'update' : function(e, ui) {
			post_order('update-menu-order');
		}
	});

	// tags
	$('table.tags #the-list').sortable({
		'items': 'tr',
		'axis': 'y',
		'helper': fixHelper,
		'update' : function(e, ui) {
			post_order('update-menu-order-tags');
		}
	});

	// users
	$('table.users #the-list').sortable({
		'items': 'tr',
		'axis': 'y',
		'helper': fixHelper,
		'update' : function(e, ui) {
			var referer = $('input[name="_wp_http_referer"]').val() || '';
			var ref = referer.split('?');
			post_order('update-menu-order-users', { referer_string: ref[1] || '' });
		}
	});

	// plugins
	$.each($('table.plugins:visible #the-list > tr'), function(i,j){
		var cb = $(this).find('input[name="checked[]"]');
		if ( cb.length && cb.attr('id') ) {
			$(this).attr('id', 'plugin_' + cb.attr('id').replace('checkbox_', ''));
		}
	});

	$('table.plugins:visible #the-list').sortable({
		'items': 'tr',
		'axis': 'y',
		'helper': fixHelper,
		'refreshPositions': true,
		'update' : function(e, ui) {
			post_order('update-menu-order-extras');
		}
	});

})(jQuery);
