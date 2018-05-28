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

$app->get('/',
function () use($app)
{
	$twig = $app['twig'];
	return $twig->render('userlogin.html.twig');
});
$app->get('/prof',
function () use($app)
{
	session_start();
	if ($_SESSION["userid"]) {
		return $app->redirect('/prof/' . $_SESSION["userid"]);
	}

	$db = $app['database'];
	$twig = $app['twig'];
	return $twig->render('proflogin.html.twig');
});
$app->get('/admin',
function () use($app)
{
	session_start();
	if ($_SESSION["Adminuserid"] == 3) {
		return $app->redirect('/admin/' . $_SESSION["Adminuserid"]);
	}

	$db = $app['database'];
	$twig = $app['twig'];
	return $twig->render('adminlogin.html.twig');
});
$app->get('/prof/logout',
function () use($app)
{
	session_start();
	session_unset();
	session_destroy();
	return $app->redirect('/prof');
});
$app->get('/admin/logout',
function () use($app)
{
	session_start();
	session_unset();
	session_destroy();
	return $app->redirect('/admin');
});
$app->get('/prof/{id}',
function ($id) use($app)
{
	if (!checkSession($app, $id)) {
		return $app->redirect('/prof');
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
$app->get('/changeveranstaltung/{id}',
function ($id) use($app)
{
	$db = $app['database'];
	$stmt = $db->prepare('SELECT dozent FROM veranstaltung WHERE idveranstaltung = :id');
	$stmt->execute([':id' => $id]);
	$dozentid = $stmt->fetch() [0];
	if (!checkSession($app, $dozentid)) {
		return $app->redirect('/prof');
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
		return $app->redirect('/prof');
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
		return $app->redirect('/prof');
	};
	$stmt = $db->prepare('UPDATE termin SET eintragenab = FROM_UNIXTIME(:zeit) WHERE idtermin = :id');
	$stmt->execute([':zeit' => time() , ':id' => $id]);
	return $app->redirect('/' . $id);
});
$app->get('/stoptimer/{id}',
function ($id) use($app)
{
	$db = $app['database'];
	$stmt = $db->prepare('SELECT veranstaltung.dozent FROM veranstaltung INNER JOIN termin ON veranstaltung.idveranstaltung = termin.veranstaltung WHERE termin.idtermin = :id');
	$stmt->execute([':id' => $id]);
	$dozentid = $stmt->fetch() [0];
	if (!checkSession($app, $dozentid)) {
		return $app->redirect('/prof');
	};
	$stmt = $db->prepare('UPDATE termin SET eintragenab = FROM_UNIXTIME(:zeit) WHERE idtermin = :id');
	$stmt->execute([':zeit' => 0000000001, ':id' => $id]);
	return $app->redirect('/' . $id);
});
$app->get('/veranstaltung/{id}',
function ($id) use($app)
{
	$db = $app['database'];
	$stmt = $db->prepare('SELECT dozent FROM veranstaltung WHERE idveranstaltung = :id');
	$stmt->execute([':id' => $id]);
	$dozentid = $stmt->fetch() [0];
	if (!checkSession($app, $dozentid)) {
		return $app->redirect('/prof');
	};
	$stmt = $db->prepare('SELECT vname FROM veranstaltung WHERE idveranstaltung = :id');
	$stmt->execute([':id' => $id]);
	$name = $stmt->fetch() [0];
	$stmt = $db->prepare('SELECT idtermin, datum FROM termin WHERE veranstaltung = :id');
	$stmt->execute([':id' => $id]);
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
$app->get('/{id}',
function ($id) use($app)
{
	$db = $app['database'];
	$candelete = false;
	$stmt = $db->prepare('SELECT veranstaltung.dozent FROM veranstaltung INNER JOIN termin ON veranstaltung.idveranstaltung = termin.veranstaltung WHERE termin.idtermin = :id');
	$stmt->execute([':id' => $id]);
	$dozentid = $stmt->fetch() [0];
	if (checkSession($app, $dozentid)) {
		$candelete = true;
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

	$angemeldet = false;
	if (!isset($_COOKIE['veranstaltungen'])) {
		$datainit = array(
			1
		);
		setcookie('veranstaltungen', json_encode($datainit));
	}

	if (isset($_COOKIE['veranstaltungen'])) {
		$data = json_decode($_COOKIE['veranstaltungen'], true);
		if (in_array($id, $data)) {
			$angemeldet = true;
		}
	}

	return $twig->render('studview.html.twig', ['info' => $info, 'anwesende' => $anwesende, 'id' => $id, 'matnr' => $_COOKIE["Matrikelnummer"], 'candelete' => $candelete, 'eintragen' => $eintragen, 'eintragzeit' => $eintragzeit, 'angemeldet' => $angemeldet]);
});
$app->post('/prof',
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
		return $app->redirect('/prof/' . $userid);
	}
	else {
		return $app->redirect('/prof');
	}
});
$app->post('/admin',
function (Request $request) use($app)
{
	$db = $app['database'];
	$login = $request->request->get('login');
	$passwort = $request->request->get('passwort');
	$passworthash = hash("sha256", $passwort);
	$userid = checkAdminLogin($login, $passworthash, $app);
	if ($userid) {
		session_start();
		$_SESSION["Adminlogin"] = $login;
		$_SESSION["Adminuserid"] = $userid;
		return $app->redirect('/admin/' . $userid);
	}
	else {
		return $app->redirect('/prof');
	}
});
$app->post('/addtermin',
function (Request $request) use($app)
{
	$db = $app['database'];
	$pid = $request->request->get('pid');
	if (!checkSession($app, $pid)) {
		return $app->redirect('/prof');
	};
	$vid = $request->request->get('vid');
	$tdatum = $request->request->get('TDatum');
	$tstart = $request->request->get('TStart');
	$tende = $request->request->get('TEnde');
	$tid = 0;
	do {
		$tid = randomNumber(6);
	}

	while (checkTermin($db, $tid));
	$stmt = $db->prepare('INSERT INTO termin (idtermin, datum, startzeit, endzeit, veranstaltung) VALUES (:tid, :tdatum, :tstart, :tende, :vid)');
	$stmt->execute([':tid' => $tid, ':tdatum' => $tdatum, ':tstart' => $tstart, ':tende' => $tende, ':vid' => $vid]);
	return $app->redirect('/prof/' . $pid);
});
$app->post('/deletestud/{id}',
function (Request $request, $id) use($app)
{
	$db = $app['database'];
	$stmt = $db->prepare('SELECT veranstaltung.dozent FROM veranstaltung INNER JOIN termin ON veranstaltung.idveranstaltung = termin.veranstaltung WHERE termin.idtermin = :id');
	$stmt->execute([':id' => $id]);
	$dozentid = $stmt->fetch() [0];
	if (!checkSession($app, $dozentid)) {
		return $app->redirect('/prof');
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
		return $app->redirect('/prof');
	};
	$db = $app['database'];
	$stmt = $db->prepare('DELETE FROM veranstaltung WHERE idveranstaltung = :id');
	$stmt->execute([':id' => $id]);
	return $app->redirect('/prof/' . $pid);
});
$app->post('/deletetermin/{id}',
function (Request $request, $id) use($app)
{
	$pid = $request->request->get('pid');
	if (!checkSession($app, $pid)) {
		return $app->redirect('/prof');
	};
	$db = $app['database'];
	$stmt = $db->prepare('DELETE FROM termin WHERE idtermin = :id');
	$stmt->execute([':id' => $id]);
	return $app->redirect('/prof/' . $pid);
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
		return $app->redirect('/prof');
	};
	$vname = $request->request->get('VName');
	$stmt = $db->prepare('UPDATE veranstaltung SET vname = :vname WHERE idveranstaltung = :id');
	$stmt->execute([':vname' => $vname, ':id' => $id]);
	return $app->redirect('/prof/' . $dozentid);
});
$app->post('/changetermin/{id}',
function (Request $request, $id) use($app)
{
	$db = $app['database'];
	$stmt = $db->prepare('SELECT veranstaltung.dozent FROM veranstaltung INNER JOIN termin ON veranstaltung.idveranstaltung = termin.veranstaltung WHERE termin.idtermin = :id');
	$stmt->execute([':id' => $id]);
	$dozentid = $stmt->fetch() [0];
	if (!checkSession($app, $dozentid)) {
		return $app->redirect('/prof');
	};
	$vdatum = $request->request->get('VDatum');
	$vstart = $request->request->get('VStart');
	$vende = $request->request->get('VEnde');
	$stmt = $db->prepare('UPDATE termin SET datum = :vdatum, startzeit = :vstart, endzeit = :vende WHERE idtermin = :id');
	$stmt->execute([':vdatum' => $vdatum, ':vstart' => $vstart, ':vende' => $vende, ':id' => $id]);
	return $app->redirect('/prof/' . $dozentid);
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
$app->post('/prof/{id}',
function (Request $request, $id) use($app)
{
	if (!checkSession($app, $id)) {
		return $app->redirect('/prof');
	};
	$db = $app['database'];
	$vname = $request->request->get('VName');
	$stmt = $db->prepare('INSERT INTO veranstaltung (idveranstaltung, vname, dozent) VALUES (:vid, :vname, :id)');
	$stmt->execute([':vid' => $vid, ':vname' => $vname, ':id' => $id]);
	return $app->redirect('/prof/' . $id);
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
	$terminid = $request->request->get('termin');
	if (checkTermin($db, $terminid)) {
		$redirect = $terminid;
	};
	return $app->redirect('/' . $redirect);
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
			$data = json_decode($_COOKIE['veranstaltungen'], true);
			array_push($data, $id);
			setcookie('veranstaltungen', json_encode($data));
		}

		$stmt = $db->prepare('INSERT INTO anwesenheit (matrikelnummer, name, termin, zeit) VALUES (:matnr, :name, :id, FROM_UNIXTIME(:zeit))');
		$stmt->execute([':id' => $id, ':matnr' => $matnr, ':name' => $name, ':zeit' => time() ]);
	}

	return $app->redirect('/' . $id);
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

function getLocalUnixtime()
{
	$dateTimeZone = new DateTimeZone("Europe/Berlin");
	$dateTime = new DateTime("now", $dateTimeZone);
	$timeOffset = $dateTimeZone->getOffset($dateTime);
	$newTime = time() + $timeOffset;
	return $newTime;
}

return $app;

