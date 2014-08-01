jQuery(document).ready(function($) {
	
	$("body .cmln-add-alias").on("click",function() {
		$create_template('');
	});

	$("body").delegate(".cmln-remove-alias", "click", function() {
		$(this).parent(".cmln-alias-field-wrap").remove();
	});

	function $create_template($value) {
		$template = $(".cmln-alias-template").html();
		$(".cmln-generated-aliases").append($template);
		$(".cmln-generated-aliases .cmln-alias-field").last().val($value);
	}

	function $populate_aliases($aliases) {
		for (var $i=0; $i<$($aliases).size(); $i++) {
			$create_template($aliases[$i]);
		}
	}

	if ($cmln_aliases) $populate_aliases($cmln_aliases);
});