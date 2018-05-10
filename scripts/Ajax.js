$(document).ready(function(e) {
	M.AutoInit();
	$('#modal1').modal({
		dismissible: true,
		onCloseEnd: function() {
			$('#VName').val("");
			$('#VDatum').val("");
			$('#VStart').val("");
			$('#VEnde').val("");
		}
	});
	$('#modal2').modal({
		dismissible: true,
		onCloseEnd: function() {
			$('#StudName').val("");
		}
	});
});
