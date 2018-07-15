<?php
/**
 * Copyright 2016 Google Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

// [START all]
use Silex\Application;
use Silex\Provider\TwigServiceProvider;
use Symfony\Component\HttpFoundation\Request;

$app = new Application();
$app->register(new TwigServiceProvider());
$app['twig.path'] = __DIR__ . "/twigs";
$app['twig']->addGlobal("CurrentUrl", $_SERVER["REQUEST_URI"]);

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

$app->get('/veranstalter',
function () use($app)
{
	session_start();
	if ($_SESSION["userid"]) {
		return $app->redirect('/veranstalter/' . $_SESSION["userid"]);
	} else if ($_SESSION["Adminuserid"] == 3) {
		return $app->redirect('/admin/' . $_SESSION["Adminuserid"]);
	}

	$db = $app['database'];
	$twig = $app['twig'];
	return $twig->render('proflogin.html.twig');
});
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
$app->get('/veranstalter/logout',
function () use($app)
{
	session_start();
	session_unset();
	session_destroy();
	return $app->redirect('/veranstalter');
});
$app->get('/admin/logout',
function () use($app)
{
	session_start();
	session_unset();
	session_destroy();
	return $app->redirect('/veranstalter');
});
$app->get('/veranstalter/{id}',
function ($id) use($app)
{
	if (!checkSession($app, $id)) {
		return $app->redirect('/veranstalter');
	};
	$db = $app['database'];
	$stmt = $db->prepare('SELECT name FROM users WHERE idusers = :id');
	$stmt->execute([':id' => $id]);
	$name = $stmt->fetch();
	$stmt = $db->prepare('SELECT * FROM veranstaltung WHERE dozent = :id');
	$stmt->execute([':id' => $id]);
	$veranstaltungen = $stmt->fetchAll();
	$max = sizeof($veranstaltungen);
	for ($i = 0; $i < $max; $i++) {
		$stmt = $db->prepare('SELECT * FROM termin WHERE veranstaltung = :id ORDER BY datum ASC');
		$stmt->execute([':id' => $veranstaltungen[$i]['idveranstaltung']]);
		$temp = $stmt->fetchAll();
		$veranstaltungen[$i]['termine'] = $temp;
	};
	$twig = $app['twig'];
	return $twig->render('profview.html.twig', ['name' => $name, 'veranstaltungen' => $veranstaltungen, 'id' => $id]);
});
$app->get('/admin/{id}',
function ($id) use($app)
{
	if (!checkAdminSession($app, $id)) {
		return $app->redirect('/admin');
	};
	$db = $app['database'];
	$stmt = $db->prepare('SELECT name FROM users WHERE idusers = :id');
	$stmt->execute([':id' => $id]);
	$name = $stmt->fetch();
	$stmt = $db->prepare('SELECT * FROM users WHERE idusers <> :id');
	$stmt->execute([':id' => $id]);
	$users = $stmt->fetchAll();
	$twig = $app['twig'];
	return $twig->render('adminview.html.twig', ['name' => $name, 'users' => $users, 'id' => $id]);
});

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

$app->get('/changeveranstaltung/{id}',
function ($id) use($app)
{
	$db = $app['database'];
	$stmt = $db->prepare('SELECT dozent FROM veranstaltung WHERE idveranstaltung = :id');
	$stmt->execute([':id' => $id]);
	$dozentid = $stmt->fetch() [0];
	if (!checkSession($app, $dozentid)) {
		return $app->redirect('/veranstalter');
	};
	$stmt = $db->prepare('SELECT * FROM veranstaltung WHERE idveranstaltung = :id');
	$stmt->execute([':id' => $id]);
	$vdata = $stmt->fetch(PDO::FETCH_ASSOC);
	$twig = $app['twig'];
	return $twig->render('changeveranstaltungview.html.twig', ['vdata' => $vdata]);
});
$app->get('/changetermin/{id}',
function ($id) use($app)
{
	$db = $app['database'];
	$stmt = $db->prepare('SELECT veranstaltung.dozent FROM veranstaltung INNER JOIN termin ON veranstaltung.idveranstaltung = termin.veranstaltung WHERE termin.idtermin = :id');
	$stmt->execute([':id' => $id]);
	$dozentid = $stmt->fetch() [0];
	if (!checkSession($app, $dozentid)) {
		return $app->redirect('/veranstalter');
	};
	$stmt = $db->prepare('SELECT * FROM termin WHERE idtermin = :id');
	$stmt->execute([':id' => $id]);
	$vdata = $stmt->fetch(PDO::FETCH_ASSOC);
	$twig = $app['twig'];
	return $twig->render('changeterminview.html.twig', ['vdata' => $vdata]);
});
$app->get('/changeuser/{id}',
function ($id) use($app)
{
	$db = $app['database'];
	if (!checkAdminSession($app, "3")) {
		return $app->redirect('/admin');
	};
	$stmt = $db->prepare('SELECT * FROM users WHERE idusers = :id');
	$stmt->execute([':id' => $id]);
	$udata = $stmt->fetch(PDO::FETCH_ASSOC);
	$twig = $app['twig'];
	return $twig->render('changeuserview.html.twig', ['udata' => $udata]);
});
$app->get('/starttimer/{id}',
function ($id) use($app)
{
	$db = $app['database'];
	$stmt = $db->prepare('SELECT veranstaltung.dozent FROM veranstaltung INNER JOIN termin ON veranstaltung.idveranstaltung = termin.veranstaltung WHERE termin.idtermin = :id');
	$stmt->execute([':id' => $id]);
	$dozentid = $stmt->fetch() [0];
	if (!checkSession($app, $dozentid)) {
		return $app->redirect('/veranstalter');
	};
	$stmt = $db->prepare('UPDATE termin SET eintragenab = FROM_UNIXTIME(:zeit), datum =DATE(FROM_UNIXTIME(:zeit)) WHERE idtermin = :id');
	$stmt->execute([':zeit' => time() , ':id' => $id]);
	return $app->redirect('/termin/' . $id);
});
$app->get('/stoptimer/{id}',
function ($id) use($app)
{
	$db = $app['database'];
	$stmt = $db->prepare('SELECT veranstaltung.dozent FROM veranstaltung INNER JOIN termin ON veranstaltung.idveranstaltung = termin.veranstaltung WHERE termin.idtermin = :id');
	$stmt->execute([':id' => $id]);
	$dozentid = $stmt->fetch() [0];
	if (!checkSession($app, $dozentid)) {
		return $app->redirect('/veranstalter');
	};
	$stmt = $db->prepare('UPDATE termin SET eintragenab = FROM_UNIXTIME(:zeit) WHERE idtermin = :id');
	$stmt->execute([':zeit' => 0000000001, ':id' => $id]);
	return $app->redirect('/termin/' . $id);
});
$app->get('/veranstaltung/{id}',
function ($id) use($app)
{
	$db = $app['database'];
	$stmt = $db->prepare('SELECT dozent FROM veranstaltung WHERE idveranstaltung = :id');
	$stmt->execute([':id' => $id]);
	$dozentid = $stmt->fetch() [0];
	if (!checkSession($app, $dozentid)) {
		return $app->redirect('/veranstalter');
	};
	$stmt = $db->prepare('SELECT vname FROM veranstaltung WHERE idveranstaltung = :id');
	$stmt->execute([':id' => $id]);
	$name = $stmt->fetch() [0];
	$stmt = $db->prepare('SELECT idtermin, datum FROM termin WHERE veranstaltung = :id AND datum < :maxdatum ORDER BY datum ASC');
	$stmt->execute([':id' => $id, ':maxdatum' => "9999-12-31"]);
	$daten = $stmt->fetchAll(PDO::FETCH_NUM);
	$stmt = $db->prepare('SELECT DISTINCT anwesenheit.matrikelnummer FROM anwesenheit INNER JOIN termin ON anwesenheit.termin = termin.idtermin INNER JOIN veranstaltung ON termin.veranstaltung = veranstaltung.idveranstaltung WHERE veranstaltung.idveranstaltung = :id');
	$stmt->execute([':id' => $id]);
	$studenten = $stmt->fetchAll();
	$anwesenheit = [];
	$maxtermin = sizeof($daten);
	$maxstud = sizeof($studenten);
	for ($i = 0; $i < $maxstud; $i++) {
		$stmt = $db->prepare('SELECT anwesenheit.termin, termin.datum FROM anwesenheit INNER JOIN termin ON anwesenheit.termin = termin.idtermin INNER JOIN veranstaltung ON termin.veranstaltung = veranstaltung.idveranstaltung WHERE veranstaltung.idveranstaltung = :id AND anwesenheit.matrikelnummer = :MNr');
		$stmt->execute([':MNr' => $studenten[$i][0], ':id' => $id]);
		$temp = $stmt->fetchAll(PDO::FETCH_NUM);
		$anwesenheit[$i] = array_fill(0, $maxtermin, false);
		foreach($temp as $datum) {
			$key = array_search($datum, $daten);
			if (!($key === false)) {
				$anwesenheit[$i][$key] = true;
			}
		}
	};
	$twig = $app['twig'];
	return $twig->render('veranstaltungview.html.twig', ['name' => $name, 'daten' => $daten, 'studenten' => $studenten, 'anwesenheit' => $anwesenheit, 'id' => $id, 'dozent' => $dozentid]);
});
$app->get('/termin/{id}',
function ($id) use($app)
{
	$db = $app['database'];
	$candelete = true;
	$stmt = $db->prepare('SELECT veranstaltung.dozent FROM veranstaltung INNER JOIN termin ON veranstaltung.idveranstaltung = termin.veranstaltung WHERE termin.idtermin = :id');
	$stmt->execute([':id' => $id]);
	$dozentid = $stmt->fetch() [0];
	if (!checkSession($app, $dozentid)) {
		return $app->redirect('/veranstalter');
	};
	$stmt = $db->prepare('SELECT users.name, veranstaltung.vname, termin.datum, termin.startzeit, termin.endzeit, veranstaltung.dozent FROM veranstaltung INNER JOIN users ON veranstaltung.dozent = users.idusers INNER JOIN termin ON veranstaltung.idveranstaltung = termin.veranstaltung WHERE termin.idtermin = :id');
	$stmt->execute([':id' => $id]);
	$info = $stmt->fetch(PDO::FETCH_ASSOC);
	$stmt = $db->prepare('SELECT matrikelnummer, name, DATE(zeit), TIME(zeit), idanwesenheit FROM anwesenheit WHERE termin = :id');
	$stmt->execute([':id' => $id]);
	$anwesende = $stmt->fetchAll();
	$twig = $app['twig'];
	$stmt = $db->prepare('SELECT eintragenab FROM termin WHERE idtermin = :id');
	$stmt->execute([':id' => $id]);
	$eintragzeit = $stmt->fetch() [0];
	$eintragzeit = strtotime($eintragzeit);
	$distance = getLocalUnixtime() - $eintragzeit;
	$eintragen = false;
	if (($distance >= 0) && ($distance <= 900)) {
		$eintragen = true;
	}
	$terminview = true;
	return $twig->render('studview.html.twig', ['info' => $info, 'anwesende' => $anwesende, 'id' => $id, 'matnr' => $_COOKIE["Matrikelnummer"], 'candelete' => $candelete, 'eintragen' => $eintragen, 'eintragzeit' => $eintragzeit, 'angemeldet' => $angemeldet, 'terminview' => $terminview]);
});

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
	$stmt = $db->prepare('SELECT veranstaltung.dozent FROM veranstaltung INNER JOIN termin ON veranstaltung.idveranstaltung = termin.veranstaltung WHERE termin.idtermin = :id');
	$stmt->execute([':id' => $tid]);
	$dozentid = $stmt->fetch() [0];
	if (checkSession($app, $dozentid)) {
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
	$distance = getLocalUnixtime() - $eintragzeit;
	$eintragen = false;
	if (($distance >= 0) && ($distance <= 900)) {
		$eintragen = true;
	}

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
$app->post('/veranstalter',
function (Request $request) use($app)
{
	$db = $app['database'];
	$login = $request->request->get('login');
	$passwort = $request->request->get('passwort');
	$passworthash = hash("sha256", $passwort);
	$userid = checkLogin($login, $passworthash, $app);
	if ($userid) {
		session_start();
		$_SESSION["login"] = $login;
		$_SESSION["userid"] = $userid;
		return $app->redirect('/veranstalter/' . $userid);
	}
	else {
		$adminid = checkAdminLogin($login, $passworthash, $app);
		if ($adminid) {
			session_start();
			$_SESSION["Adminlogin"] = $login;
			$_SESSION["Adminuserid"] = $adminid;
			return $app->redirect('/admin/' . $adminid);
		} else {
			return $app->redirect('/veranstalter');
		}
	}
});
$app->post('/addtermin',
function (Request $request) use($app)
{
	$db = $app['database'];
	$pid = $request->request->get('pid');
	if (!checkSession($app, $pid)) {
		return $app->redirect('/veranstalter');
	};
	$vid = $request->request->get('vid');
	$tdatum = "9999-12-31";
	$tstart = $request->request->get('TStart');
	$tende = $request->request->get('TEnde');
	$tid = 0;
	do {
		$tid = randomNumber(8);
	}
	while (checkTermin($db, $tid));
	$stmt = $db->prepare('INSERT INTO termin (idtermin, datum, startzeit, endzeit, veranstaltung) VALUES (:tid, :tdatum, :tstart, :tende, :vid)');
	$stmt->execute([':tid' => $tid, ':tdatum' => $tdatum, ':tstart' => $tstart, ':tende' => $tende, ':vid' => $vid]);
	return $app->redirect('/veranstalter/' . $pid);
});
$app->post('/deletestud/{id}',
function (Request $request, $id) use($app)
{
	$db = $app['database'];
	$stmt = $db->prepare('SELECT veranstaltung.dozent FROM veranstaltung INNER JOIN termin ON veranstaltung.idveranstaltung = termin.veranstaltung WHERE termin.idtermin = :id');
	$stmt->execute([':id' => $id]);
	$dozentid = $stmt->fetch() [0];
	if (!checkSession($app, $dozentid)) {
		return $app->redirect('/veranstalter');
	};
	$anwid = $request->request->get('anwid');
	$stmt = $db->prepare('DELETE FROM anwesenheit WHERE idanwesenheit = :id');
	$stmt->execute([':id' => $anwid]);
	return $app->redirect('/' . $id);
});
$app->post('/deleteveranstaltung/{id}',
function (Request $request, $id) use($app)
{
	$pid = $request->request->get('pid');
	if (!checkSession($app, $pid)) {
		return $app->redirect('/veranstalter');
	};
	$db = $app['database'];
	$stmt = $db->prepare('DELETE FROM veranstaltung WHERE idveranstaltung = :id');
	$stmt->execute([':id' => $id]);
	return $app->redirect('/veranstalter/' . $pid);
});
$app->post('/deletetermin/{id}',
function (Request $request, $id) use($app)
{
	$pid = $request->request->get('pid');
	if (!checkSession($app, $pid)) {
		return $app->redirect('/veranstalter');
	};
	$db = $app['database'];
	$stmt = $db->prepare('DELETE FROM termin WHERE idtermin = :id');
	$stmt->execute([':id' => $id]);
	return $app->redirect('/veranstalter/' . $pid);
});
$app->post('/deleteuser/{id}',
function (Request $request, $id) use($app)
{
	$pid = $request->request->get('pid');
	if (!checkAdminSession($app, $pid)) {
		return $app->redirect('/admin');
	};
	$db = $app['database'];
	$stmt = $db->prepare('DELETE FROM users WHERE idusers = :id');
	$stmt->execute([':id' => $id]);
	return $app->redirect('/admin/' . $pid);
});
$app->post('/changeveranstaltung/{id}',
function (Request $request, $id) use($app)
{
	$db = $app['database'];
	$stmt = $db->prepare('SELECT dozent FROM veranstaltung WHERE idveranstaltung = :id');
	$stmt->execute([':id' => $id]);
	$dozentid = $stmt->fetch() [0];
	if (!checkSession($app, $dozentid)) {
		return $app->redirect('/veranstalter');
	};
	$vname = $request->request->get('VName');
	$stmt = $db->prepare('UPDATE veranstaltung SET vname = :vname WHERE idveranstaltung = :id');
	$stmt->execute([':vname' => $vname, ':id' => $id]);
	return $app->redirect('/veranstalter/' . $dozentid);
});
$app->post('/changetermin/{id}',
function (Request $request, $id) use($app)
{
	$db = $app['database'];
	$stmt = $db->prepare('SELECT veranstaltung.dozent FROM veranstaltung INNER JOIN termin ON veranstaltung.idveranstaltung = termin.veranstaltung WHERE termin.idtermin = :id');
	$stmt->execute([':id' => $id]);
	$dozentid = $stmt->fetch() [0];
	if (!checkSession($app, $dozentid)) {
		return $app->redirect('/veranstalter');
	};
	$vdatum = $request->request->get('VDatum');
	$vstart = $request->request->get('VStart');
	$vende = $request->request->get('VEnde');
	$stmt = $db->prepare('UPDATE termin SET datum = :vdatum, startzeit = :vstart, endzeit = :vende WHERE idtermin = :id');
	$stmt->execute([':vdatum' => $vdatum, ':vstart' => $vstart, ':vende' => $vende, ':id' => $id]);
	return $app->redirect('/veranstalter/' . $dozentid);
});
$app->post('/changeuser/{id}',
function (Request $request, $id) use($app)
{
	$db = $app['database'];
	if (!checkAdminSession($app, 3)) {
		return $app->redirect('/admin');
	};
	$dlogin = $request->request->get('DLogin');
	$dpass = $request->request->get('DPass');
	$dname = $request->request->get('DName');
	$stmt = $db->prepare('UPDATE users SET login = :login, passwort = :passwort, name = :name WHERE idusers = :id');
	$stmt->execute([':login' => $dlogin, ':passwort' => hash("sha256", $dpass) , ':name' => $dname, ':id' => $id]);
	return $app->redirect('/admin/' . "3");
});
$app->post('/veranstalter/{id}',
function (Request $request, $id) use($app)
{
	if (!checkSession($app, $id)) {
		return $app->redirect('/veranstalter');
	};
	$db = $app['database'];
	$vid = 0;
	do {
		$vid = randomNumber(6);
	}
	while (checkVeranstaltung($db, $vid));
	$vname = $request->request->get('VName');
	$stmt = $db->prepare('INSERT INTO veranstaltung (idveranstaltung, vname, dozent) VALUES (:vid, :vname, :id)');
	$stmt->execute([':vid' => $vid, ':vname' => $vname, ':id' => $id]);
	return $app->redirect('/veranstalter/' . $id);
});
$app->post('/admin/{id}',
function (Request $request, $id) use($app)
{
	if (!checkAdminSession($app, $id)) {
		return $app->redirect('/admin');
	};
	$db = $app['database'];
	$dlogin = $request->request->get('DLogin');
	$dpass = $request->request->get('DPass');
	$dname = $request->request->get('DName');
	$stmt = $db->prepare('INSERT INTO users (login, passwort, name, isadmin) VALUES (:login, :passwort, :name, 0)');
	$stmt->execute([':login' => $dlogin, ':passwort' => hash("sha256", $dpass) , ':name' => $dname]);
	return $app->redirect('/admin/' . $id);
});
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
	$userid = checkLogin($login, $passworthash, $app);
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

$app->post('/sendinvite',
function (Request $request) use($app)
{
	$db = $app['database'];
	$email = $request->request->get('EMail');
	$mess = $request->request->get('InvMess');
	$pid = $request->request->get('pid');
	$pname = $request->request->get('pname');
	if (!checkSession($app, $pid)) {
		return $app->redirect('/veranstalter');
	};
	$invitecode = hash("sha256", randomNumber(32));
	$stmt = $db->prepare('INSERT INTO invitecodes (invitecode, createdBy) VALUES (:invitecode, :pid)');
	$stmt->execute([':invitecode' => $invitecode, ':pid' => $pid]);
	date_default_timezone_set('Europe/Berlin');
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

$app->post('/bugreport',
function (Request $request) use($app)
{
	$currentURL = $request->request->get('currentURL');
	$brname = $request->request->get('BRName');
	$bname = $request->request->get('BName');
	$brep = $request->request->get('BRep');
	date_default_timezone_set('Europe/Berlin');
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

$app->post('/termin/{id}',
function (Request $request, $id) use($app)
{
	$db = $app['database'];
	$candelete = true;
	$stmt = $db->prepare('SELECT veranstaltung.dozent FROM veranstaltung INNER JOIN termin ON veranstaltung.idveranstaltung = termin.veranstaltung WHERE termin.idtermin = :id');
	$stmt->execute([':id' => $id]);
	$dozentid = $stmt->fetch() [0];
	if (!checkSession($app, $dozentid)) {
		return $app->redirect('/veranstalter');
	};

	$matnr = $request->request->get('MNr');
	$name = $request->request->get('StudName');
	
	$stmt = $db->prepare('INSERT INTO anwesenheit (matrikelnummer, name, termin, zeit) VALUES (:matnr, :name, :id, FROM_UNIXTIME(:zeit))');
	$stmt->execute([':id' => $id, ':matnr' => $matnr, ':name' => $name, ':zeit' => time() ]);


	return $app->redirect('/termin/' . $id);
});

$app->post('/{id}',
function (Request $request, $id) use($app)
{
	$db = $app['database'];
	$candelete = false;
	$stmt = $db->prepare('SELECT veranstaltung.dozent FROM veranstaltung INNER JOIN termin ON veranstaltung.idveranstaltung = termin.veranstaltung WHERE termin.idtermin = :id');
	$stmt->execute([':id' => $id]);
	$dozentid = $stmt->fetch() [0];
	if (checkSession($app, $dozentid)) {
		$candelete = true;
	};
	$stmt = $db->prepare('SELECT eintragenab FROM termin WHERE idtermin = :id');
	$stmt->execute([':id' => $id]);
	$eintragzeit = $stmt->fetch() [0];
	$eintragzeit = strtotime($eintragzeit);
	$distance = getLocalUnixtime() - $eintragzeit;
	$eintragen = false;
	if ((($distance >= 0) && ($distance <= 900)) || $candelete) {
		$eintragen = true;
	}

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
	
	$URLaddition = "";
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
$app['database'] =
function () use($app)
{
	$dsn = getenv('MYSQL_DSN');
	$user = getenv('MYSQL_USER');
	$password = getenv('MYSQL_PASSWORD');
	if (!isset($dsn, $user) || false === $password) {
		throw new Exception('Set MYSQL_DSN, MYSQL_USER, and MYSQL_PASSWORD environment variables');
	}

	$db = new PDO($dsn, $user, $password);
	return $db;
};

function checkLogin($login, $passwort, $app)
{
	$db = $app['database'];
	$stmt = $db->prepare('SELECT idusers FROM users WHERE login = :login AND passwort = :passwort');
	$stmt->execute([':login' => $login, ':passwort' => $passwort]);
	$userid = $stmt->fetchColumn();
	$stmt = $db->prepare('SELECT isadmin FROM users WHERE idusers = :id');
	$stmt->execute([':id' => $userid]);
	$isadmin = $stmt->fetchColumn();
	if (empty($userid) || $isadmin) {
		return "";
	}
	else {
		return $userid;
	}
}

function checkAdminLogin($login, $passwort, $app)
{
	$db = $app['database'];
	$stmt = $db->prepare('SELECT idusers FROM users WHERE login = :login AND passwort = :passwort');
	$stmt->execute([':login' => $login, ':passwort' => $passwort]);
	$userid = $stmt->fetchColumn();
	$stmt = $db->prepare('SELECT isadmin FROM users WHERE idusers = :id');
	$stmt->execute([':id' => $userid]);
	$isadmin = $stmt->fetchColumn();
	if (empty($userid) || !$isadmin) {
		return "";
	}
	else {
		return $userid;
	}
}

function checkSession($app, $userid)
{
	session_start();
	$result = false;
	if ($_SESSION["userid"] == $userid) {
		$result = true;
	}

	return $result;
}

function checkAdminSession($app, $userid)
{
	session_start();
	$result = false;
	if ($_SESSION["Adminuserid"] == $userid) {
		$result = true;
	}

	return $result;
}

function randomNumber($length)
{
	$result = '';
	for ($i = 0; $i < $length; $i++) {
		$result.= mt_rand(0, 9);
	}

	return $result;
}

function checkTermin($db, $tid)
{
	$stmt = $db->prepare('SELECT EXISTS(SELECT * FROM termin WHERE idtermin = :id)');
	$stmt->execute([':id' => $tid]);
	return $stmt->fetch() [0];
}

function checkVeranstaltung($db, $vid)
{
	$stmt = $db->prepare('SELECT EXISTS(SELECT * FROM veranstaltung WHERE idveranstaltung = :id)');
	$stmt->execute([':id' => $vid]);
	return $stmt->fetch() [0];
}

function checkVeranstaltungValid($db, $vid)
{
	$stmt = $db->prepare('SELECT eintragenab FROM termin WHERE veranstaltung = :id AND datum < :maxdatum ORDER BY datum DESC, startzeit DESC');
	$stmt->execute([':id' => $vid, ':maxdatum' => "9999-12-31"]);
	$eintragzeit = $stmt->fetch() [0];
	$eintragzeit = strtotime($eintragzeit);
	$distance = getLocalUnixtime() - $eintragzeit;
	$eintragen = false;
	if (($distance >= 0) && ($distance <= 900)) {
		$eintragen = true;
	}
	return $eintragen;
}

function getLocalUnixtime()
{
	$dateTimeZone = new DateTimeZone("Europe/Berlin");
	$dateTime = new DateTime("now", $dateTimeZone);
	$timeOffset = $dateTimeZone->getOffset($dateTime);
	$newTime = time() + $timeOffset;
	return $newTime;
}

return $app;

