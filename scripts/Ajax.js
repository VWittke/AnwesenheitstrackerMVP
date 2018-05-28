$(document).ready(function(e) {
	M.AutoInit();
	$('#modal1').modal({
		dismissible: true,
		onCloseEnd: function() {
			$('#VName').val("");
		}
	});
	$('#modal2').modal({
		dismissible: true,
		onCloseEnd: function() {
			$('#StudName').val("");
		}
	});
	$('#modal3').modal({
		dismissible: true,
		onCloseEnd: function() {
			$('#TDatum').val("");
			$('#TStart').val("");
			$('#TEnde').val("");
			$('#vid').val("");
		},
	});
});

function addVID(e) {
	$('#vid').val(e.parentNode.id);
};

function startTimer(expiration, display) {
	var date = new Date(expiration*1000);
	var now = new Date().getTime();
	var distance = date - now;
	var offset = Math.floor(distance / 360000);
	var x = setInterval(function timer() {
		var date = new Date(expiration*1000);
		var now = new Date().getTime();
		var distance = date - now;
		var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
		var seconds = Math.floor((distance % (1000 * 60)) / 1000);
		display.textContent = ("0" + minutes).slice(-2) + ":" + ("0" + seconds).slice(-2); 
		 if ((distance - (offset * 360000)) < 0) {
			clearInterval(x);
			display.textContent = "Zeit abgelaufen";
			window.location.reload(true);
		}
	},1000);
};

function initTimer(timestamp) {
	var expiration = timestamp + 900,
	display = document.querySelector('#time');
	startTimer(expiration, display);
};
