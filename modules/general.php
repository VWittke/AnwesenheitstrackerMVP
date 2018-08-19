<?php

/**
 * Alle Seitenaufrufe, die einen normalen User betreffen.
 */
use Symfony\Component\HttpFoundation\Request;

//======================================================================
// STARTSEITE
//======================================================================

//-----------------------------------------------------
// Get-Requests

//Startseite
$app->get('/',
function () use($app)
{
	session_start();
	if (isset($_SESSION['message'])) {
		$displaymessage = $_SESSION['message'];
		unset($_SESSION['message']);
	}
	$twig = $app['twig'];
	return $twig->render('userlogin.html.twig', ['displaymessage' => $displaymessage]);
});

//-----------------------------------------------------
// Post-Requests

//Suchen einer Veranstaltung per Code
$app->post('/',
function (Request $request) use($app)
{
	$db = $app['database'];
	$redirect = "";
	$vid = $request->request->get('veranstaltung');
	if (checkVeranstaltung($db, $vid)) {
		if (checkVeranstaltungValid($db, $vid)) {
			$redirect = $vid;
		} else {
			session_start();
			$_SESSION["message"] = 3;
		}
	} else {
		session_start();
		$_SESSION["message"] = 2;
	};
	return $app->redirect('/' . $redirect);
});

//======================================================================
// TERMINSEITE
//======================================================================

//-----------------------------------------------------
// Get-Requests

//Anzeigen der Seite eines gültigen Termins
$app->get('/{id}',
function ($id) use($app)
{
	$db = $app['database'];
	$stmt = $db->prepare('SELECT idtermin FROM termin WHERE veranstaltung = :id AND datum < :maxdatum ORDER BY datum DESC, startzeit DESC');
	$stmt->execute([':id' => $id, ':maxdatum' => "9999-12-31"]);
	$tid = $stmt->fetch() [0];
	if (!$tid) {
		return $app->redirect('/');
	}
	$displaymessage = false;
	session_start();
	if (isset($_SESSION['message'])) {
		$displaymessage = true;
		unset($_SESSION['message']);
	}
	$candelete = false;
	$dozentid = getDozentForTermin($db, $tid);
	if (checkSession($app, $dozentid, 'userid')) {
		$candelete = true;
	};
	$stmt = $db->prepare('SELECT users.name, veranstaltung.vname, termin.datum, termin.startzeit, termin.endzeit, veranstaltung.dozent FROM veranstaltung INNER JOIN users ON veranstaltung.dozent = users.idusers INNER JOIN termin ON veranstaltung.idveranstaltung = termin.veranstaltung WHERE termin.idtermin = :id');
	$stmt->execute([':id' => $tid]);
	$info = $stmt->fetch(PDO::FETCH_ASSOC);
	$stmt = $db->prepare('SELECT matrikelnummer, name, DATE(zeit), TIME(zeit), idanwesenheit FROM anwesenheit WHERE termin = :id');
	$stmt->execute([':id' => $tid]);
	$anwesende = $stmt->fetchAll();
	$twig = $app['twig'];
	$stmt = $db->prepare('SELECT eintragenab FROM termin WHERE idtermin = :id');
	$stmt->execute([':id' => $tid]);
	$eintragzeit = $stmt->fetch() [0];
	$eintragzeit = strtotime($eintragzeit);
	$eintragen = checkAnmeldeZeitraum($eintragzeit, 900);

	$angemeldet = false;
	if (!isset($_COOKIE['veranstaltungen'])) {
		$datainit = array(
			1
		);
		setcookie('veranstaltungen', json_encode($datainit));
	}
	
	if (!isset($_COOKIE['termine'])) {
		$datainit = array(
			1
		);
		setcookie('termine', json_encode($datainit));
	}

	if (isset($_COOKIE['termine'])) {
		$data = json_decode($_COOKIE['termine'], true);
		if (in_array($tid, $data)) {
			$angemeldet = true;
		}
	}
	$terminview = false;
	return $twig->render('studview.html.twig', ['info' => $info, 'anwesende' => $anwesende, 'id' => $tid, 'matnr' => $_COOKIE["Matrikelnummer"], 'candelete' => $candelete, 'eintragen' => $eintragen, 'eintragzeit' => $eintragzeit, 'angemeldet' => $angemeldet, 'terminview' => $terminview, 'displaymessage' => $displaymessage]);
});

//-----------------------------------------------------
// Post-Requests

// Eintragen im Termin durch Teilnehmer
$app->post('/{id}',
function (Request $request, $id) use($app)
{
	$db = $app['database'];
	$candelete = false;
	$dozentid = getDozentForTermin($db, $id);
	if (checkSession($app, $dozentid, 'userid')) {
		$candelete = true;
	};
	$stmt = $db->prepare('SELECT eintragenab FROM termin WHERE idtermin = :id');
	$stmt->execute([':id' => $id]);
	$eintragzeit = $stmt->fetch() [0];
	$eintragzeit = strtotime($eintragzeit);
	$eintragen = checkAnmeldeZeitraum($eintragzeit, 900);

	if ($eintragen) {
		$matnr = $request->request->get('MNr');
		$name = $request->request->get('StudName');
		setcookie("Matrikelnummer", $matnr);
		if (!$candelete) {
			$data = json_decode($_COOKIE['termine'], true);
			array_push($data, $id);
			setcookie('termine', json_encode($data));
		}

		$stmt = $db->prepare('INSERT INTO anwesenheit (matrikelnummer, name, termin, zeit) VALUES (:matnr, :name, :id, FROM_UNIXTIME(:zeit))');
		$stmt->execute([':id' => $id, ':matnr' => $matnr, ':name' => $name, ':zeit' => time() ]);
	}
	$stmt = $db->prepare('SELECT veranstaltung.idveranstaltung FROM veranstaltung INNER JOIN termin ON veranstaltung.idveranstaltung = termin.veranstaltung WHERE termin.idtermin = :id');
	$stmt->execute([':id' => $id]);
	$vid = $stmt->fetch() [0];
	
	if (isset($_COOKIE['veranstaltungen'])) {
		$data = json_decode($_COOKIE['veranstaltungen'], true);
		if (!in_array($vid, $data)) {
			session_start();
			$_SESSION["message"] = 1;
		}
	}
	if (!$candelete) {
		$data = json_decode($_COOKIE['veranstaltungen'], true);
		if (!in_array($vid, $data)) {
			array_push($data, $vid);
		}
		setcookie('veranstaltungen', json_encode($data));
	}
	return $app->redirect('/' . $vid);
});

//======================================================================
// BUGREPORTING
//======================================================================

//-----------------------------------------------------
// Post-Requests

//Versenden eines Bugreports
$app->post('/bugreport',
function (Request $request) use($app)
{
	$currentURL = $request->request->get('currentURL');
	$brname = $request->request->get('BRName');
	$bname = $request->request->get('BName');
	$brep = $request->request->get('BRep');
	$to      = 'vitusw@gmx.net,fls@fh-wedel.de';
	$subject = 'Bug-Report ' . date("D M j G:i:s T Y")  . ' : ' . $bname;
	$message = '
			<html>
				<head>
					<title>Bug-Report ' . date("D M j G:i:s T Y")  . '</title>
				</head>
				<body>
					<h4>Um ' . date("D M j G:i:s T Y")  . ' schickte ' . $brname . ' folgenden Bug-Report:</h4>
					<p>' . $bname . ':<p>
					<p>' . $brep . '</p>
					<p>Gesendet von der URI: ' . $currentURL . '</p>
				</body>
			</html>
			';
	$headers[] = 'MIME-Version: 1.0';
	$headers[] = 'Content-type: text/html; charset=iso-8859-1';
	$headers[] = 'From: BugTracker@anwesenheitstrackermvp.appspotmail.com';
	mail($to, $subject, $message, implode("\r\n", $headers));
	return $app->redirect($currentURL);
});