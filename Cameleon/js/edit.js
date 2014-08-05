jQuery(document).ready(function($) {

	$template_class = ".cmln-alias-template";
	$alias_class = ".cmln-generated-aliases";
	$field_class = ".cmln-alias-field";
	$add_class = ".cmln-add-alias";
	$remove_class = ".cmln-remove-alias";
	$wrap_class = ".cmln-alias-field-wrap";

	function $create_template($value) {
		$template = $($template_class).html();
		$count = $($field_class).size();

		$($alias_class).append($template);
		$id = $($alias_class + " " + $field_class).last().attr("id");

		$($alias_class + " " + $field_class).last().val($value);
		$($alias_class + " " + $field_class).last().attr("id",$id + "_" + $count);
	}

	function $validate_fields() {
		var sel = ".cmln-desc";
		$(sel).html("Validating...");

		var fields = {};
		$($alias_class + " " + $field_class).each(function() {
			fields[$(this).attr("id")] = $(this).val();
		});
		
		$.ajax({
			 type : "POST"
			,url : cmln.url
			,data : {
				 action : cmln.action
				,aliases : fields
				,post : $("#post_ID").val()
			}
		})
		.done(function(data) {
			$($alias_class + " " + $field_class).removeClass("cmln-field-error");
			try { 
				var response = $.parseJSON(data);
				var prev = $(sel).html();

				if (response.status==500) {
					$(sel).addClass("cmln-error");
					$.each(response.errors, function() {
						$("#" + this).addClass("cmln-field-error");
					});
				}
				else if (response.status==202) {
					$("#publish").attr("disabled",null);
					$(sel).removeClass("cmln-error");
				}

				console.log(response.extra);

				$(sel).html(response.message);
			}
			catch(e) {
				console.log(e);
			}
		});
	}
	
	$("body").delegate($remove_class, "click", function() {
		$(this).parent($wrap_class).remove();
		$validate_fields()
	});

	$($add_class).on("click",function() { $create_template(''); });
	$("body").delegate($field_class, "keydown", function() { $("#publish").attr("disabled","disabled"); });
	$("body").delegate($field_class, "change", function() {	 $validate_fields()	});
});