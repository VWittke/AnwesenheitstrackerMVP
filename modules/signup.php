<?php

/**
 * Alle Seitenaufrufe, die die Neuanmeldung von Usern betreffen.
 */
use Symfony\Component\HttpFoundation\Request;

//======================================================================
// VON STARTSEITE
//======================================================================

//-----------------------------------------------------
// Get-Requests

//Anlegen neuer Veranstaltung von Startseite
$app->get('/anlegen',
function () use($app)
{
	session_start();
	if (isset($_SESSION['message'])) {
		$displaymessage = $_SESSION['message'];
		unset($_SESSION['message']);
	}
	$twig = $app['twig'];
	return $twig->render('addveranstaltunganduser.html.twig', ['displaymessage' => $displaymessage]);
});

//-----------------------------------------------------
// Post-Requests

//Anlegen eines neuen Users und Veranstaltung von Startseite aus
$app->post('/registernewuser',
function (Request $request) use($app)
{
	$db = $app['database'];
	$vname = $request->request->get('VName');
	$pname = $request->request->get('PName');
	$login = $request->request->get('Login');
	$pass1 = $request->request->get('Pass1');
	$pass2 = $request->request->get('Pass2');
	$stmt = $db->prepare('SELECT EXISTS(SELECT * FROM users WHERE login = :login)');
	$stmt->execute([':login' => $login]);
	if ($stmt->fetch()[0]) {
		session_start();
		$_SESSION["message"] = 4;
		return $app->redirect('/anlegen');
	}
	if ($pass1 != $pass2) {
		session_start();
		$_SESSION["message"] = 5;
		return $app->redirect('/anlegen');
	}
	$passworthash = hash("sha256", $pass1);
	$stmt = $db->prepare('INSERT INTO users (login, passwort, name, isadmin) VALUES (:login, :passwort, :name, 0)');
	$stmt->execute([':login' => $login, ':passwort' => $passworthash, ':name' => $pname]);
	$userid = checkLogin($login, $passworthash, $app, false);
	$vid = 0;
	do {
		$vid = randomNumber(6);
	}
	while (checkVeranstaltung($db, $vid));
	$stmt = $db->prepare('INSERT INTO veranstaltung (idveranstaltung, vname, dozent) VALUES (:vid, :vname, :id)');
	$stmt->execute([':vid' => $vid, ':vname' => $vname, ':id' => $userid]);
	session_start();
	$_SESSION["login"] = $login;
	$_SESSION["userid"] = $userid;
	return $app->redirect('/veranstalter/' . $userid);
});

//======================================================================
// PER INVITECODE (NICHT MEHR UNTERSTÜTZT)
//======================================================================

//-----------------------------------------------------
// Get-Requests

//Per Invitecode registrieren (nicht mehr möglich)
$app->get('/register/{invitecode}',
function ($invitecode) use($app)
{
	if (isset($_GET['err'])) {
		$displaymessage = $_GET['err'];
	}
	$db = $app['database'];
	$stmt = $db->prepare('SELECT used FROM invitecodes WHERE invitecode = :invitecode');
	$stmt->execute([':invitecode' => $invitecode]);
	$usestate = $stmt->fetch();
	if (!is_array($usestate) || $usestate[0] == 1) {
		return $app->redirect('/');
	}
	$twig = $app['twig'];
	return $twig->render('registernewuser.html.twig', ['invitecode' => $invitecode, 'displaymessage' => $displaymessage]);
});

//-----------------------------------------------------
// Post-Requests

//Versenden eines Invites (nicht mehr möglich)
$app->post('/sendinvite',
function (Request $request) use($app)
{
	$db = $app['database'];
	$email = $request->request->get('EMail');
	$mess = $request->request->get('InvMess');
	$pid = $request->request->get('pid');
	$pname = $request->request->get('pname');
	if (!checkSession($app, $pid, 'userid')) {
		return $app->redirect('/veranstalter');
	};
	$invitecode = hash("sha256", randomNumber(32));
	$stmt = $db->prepare('INSERT INTO invitecodes (invitecode, createdBy) VALUES (:invitecode, :pid)');
	$stmt->execute([':invitecode' => $invitecode, ':pid' => $pid]);
	$to      = $email;
	$subject = $pname . ' hat Sie eingeladen den Anwesenheitstracker zu testen';
	$message = '
			<html>
				<head>
					<title>Einladung ' . $invitecode . '</title>
				</head>
				<body>
					<h4>' . $pname . ' hat Sie eingeladen den Anwesenheitstracker zu testen</h4>
					<p>Sie können sich Ihren eigenen Account unter folgendem Link erstellen:<p>
					<a href="https://anwesenheitstrackermvp.appspot.com/register/' . $invitecode . '">https://anwesenheitstrackermvp.appspot.com/register/' . $invitecode . '</a>
					<p>' . $pname . ' hat folgende Nachricht für Sie:</p>
					<p>' . $mess . '</p>
				</body>
			</html>
			';
	$headers[] = 'MIME-Version: 1.0';
	$headers[] = 'Content-type: text/html; charset=iso-8859-1';
	$headers[] = 'From: InviteService@anwesenheitstrackermvp.appspotmail.com';
	mail($to, $subject, $message, implode("\r\n", $headers));
	return $app->redirect('/veranstalter');
});

//Erstellen eines Users per Invitecode (nicht mehr möglich)
$app->post('/register/{invitecode}',
function (Request $request, $invitecode) use($app)
{
	$db = $app['database'];
	$stmt = $db->prepare('SELECT used FROM invitecodes WHERE invitecode = :invitecode');
	$stmt->execute([':invitecode' => $invitecode]);
	$usestate = $stmt->fetch();
	if (!is_array($usestate) || $usestate[0] == 1) {
		return $app->redirect('/');
	}
	$pname = $request->request->get('PName');
	$login = $request->request->get('Login');
	$pass1 = $request->request->get('Pass1');
	$pass2 = $request->request->get('Pass2');
	$stmt = $db->prepare('SELECT EXISTS(SELECT * FROM users WHERE login = :login)');
	$stmt->execute([':login' => $login]);
	if ($stmt->fetch()[0]) {
		return $app->redirect('/register/' . $invitecode . '?err=2');
	}
	if ($pass1 != $pass2) {
		return $app->redirect('/register/' . $invitecode . '?err=3');
	}
	$stmt = $db->prepare('INSERT INTO users (login, passwort, name, isadmin) VALUES (:login, :passwort, :name, 0)');
	$stmt->execute([':login' => $login, ':passwort' => hash("sha256", $pass1) , ':name' => $pname]);
	$stmt = $db->prepare('UPDATE invitecodes SET used = 1 WHERE invitecode = :invitecode');
	$stmt->execute([':invitecode' => $invitecode]);
	return $app->redirect('/veranstalter');
});