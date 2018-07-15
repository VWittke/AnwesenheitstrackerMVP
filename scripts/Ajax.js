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
		onOpenStart: function() {
			var currentdate = new Date();
			var in15Mins = new Date(+new Date() + (15 * 60 * 1000))
			$('#TStart').val(currentdate.getHours() + ":" + (("0" + currentdate.getMinutes()).slice(-2)));
			$('#TEnde').val(in15Mins.getHours() + ":" + (("0" + in15Mins.getMinutes()).slice(-2)));
		},
		onCloseEnd: function() {
			$('#TDatum').val("");
			$('#TStart').val("");
			$('#TEnde').val("");
			$('#vid').val("");
		},
	});
	$('#modal4').modal({
		dismissible: true,
		onCloseEnd: function() {
			$('#BRName').val("");
			$('#BName').val("");
			$('#BRep').val("");
		},
	});
	$('#modal5').modal({
		dismissible: true,
		onCloseEnd: function() {
			$('#EMail').val("");
			$('#InvMess').val("");
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

function notifyUser(messageid) {
	var message = "NotSet";
	switch(messageid) {
		case 1:
			message = "Sie können und sollten sich diese Seite bookmarken, um in Folgeveranstaltungen leichteren Zugang zu dieser Seite zu haben.";
			break;
		case 2:
			message = "Zu dem angegebenen Code existiert keine Veranstaltung.";
			break;
		case 3:
			message = "Zu dem angegebenen Code läuft aktuell keine Veranstaltung.";
			break;
		case 4:
			message = "Der angegebenene Login ist schon vergeben. Wählen Sie bitte einen anderen.";
			break;
		case 5:
			message = "Die eingegebenen Passwörter stimmen leider nicht überein.";
			break;
	}
	alert(message);
}
