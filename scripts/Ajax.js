var messages = [
    "Not set",
    "Sie können und sollten sich diese Seite bookmarken, um in Folgeveranstaltungen leichteren Zugang zu dieser Seite zu haben.",
    "Zu dem angegebenen Code existiert keine Veranstaltung.",
	"Zu dem angegebenen Code läuft aktuell keine Veranstaltung.",
	"Der angegebenene Login ist schon vergeben. Wählen Sie bitte einen anderen.",
	"Die eingegebenen Passwörter stimmen leider nicht überein."
];

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
	$('.timepicker').timepicker({
		twelveHour: false,
		autoClose: true,
		i18n: {
			cancel: "Abbrechen",
			done: "Fertig",
		},
	});
	$('.datepicker').datepicker({
		format: 'yyyy-mm-dd',
		autoClose: true,
		firstDay: 1,
		i18n: {
			cancel: "Abbrechen",
			done: "Fertig",
			months: ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'],
			monthsShort: ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'],
			weekdays: ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'],
			weekdaysShort: ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'],
			weekdaysAbbrev: ['S','M','D','M','D','F','S'],
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
	$(document).ready(function() {
		var out = "NotSet";
		out = messages[messageid];
		$('#modal6').modal({
			dismissible: true
		});
		$('#notification').text(out);
		$('#modal6').modal('open');
	});
}
