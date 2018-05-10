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

$app->get('/', function () use ($app)
{
	$twig = $app['twig'];
	return $twig->render('userlogin.html.twig');
});

$app->get('/prof', function () use ($app)
{
	session_start();
	if ($_SESSION["userid"])
	{
		return $app->redirect('/prof/' . $_SESSION["userid"]);
	}
	$db = $app['database'];
	$twig = $app['twig'];
	return $twig->render('proflogin.html.twig');
});

$app->get('/prof/logout', function () use ($app)
{
	session_start();
	session_unset();
	session_destroy();
	return $app->redirect('/prof');
});

$app->get('/prof/{id}', function ($id) use ($app)
{
	if (!checkSession($app, $id))
	{
		return $app->redirect('/prof');
	};
	$db = $app['database'];
	$stmt = $db->prepare('SELECT name FROM users WHERE idusers = :id');
	$stmt->execute([':id' => $id]);
	$name = $stmt->fetch();
	$stmt = $db->prepare('SELECT * FROM veranstaltung WHERE dozent = :id');
	$stmt->execute([':id' => $id]);
	$veranstaltungen = $stmt->fetchAll();
	$twig = $app['twig'];
	return $twig->render('profview.html.twig', ['name' => $name, 'veranstaltungen' => $veranstaltungen, 'id' => $id]);
});

$app->get('/changeveranstaltung/{id}', function ($id) use ($app)
{
	$db = $app['database'];
	$stmt = $db->prepare('SELECT dozent FROM veranstaltung WHERE idveranstaltung = :id');
	$stmt->execute([':id' => $id]);
	$dozentid = $stmt->fetch() [0];
	if (!checkSession($app, $dozentid))
	{
		return $app->redirect('/prof');
	};
	$stmt = $db->prepare('SELECT * FROM veranstaltung WHERE idveranstaltung = :id');
	$stmt->execute([':id' => $id]);
	$vdata = $stmt->fetch(PDO::FETCH_ASSOC);
	$twig = $app['twig'];
	return $twig->render('changeview.html.twig', ['vdata' => $vdata]);
});

$app->get('/{id}', function ($id) use ($app)
{
	$db = $app['database'];
	$candelete = false;
	$stmt = $db->prepare('SELECT dozent FROM veranstaltung WHERE idveranstaltung = :id');
	$stmt->execute([':id' => $id]);
	$dozentid = $stmt->fetch() [0];
	if (checkSession($app, $dozentid))
	{
		$candelete = true;
	};
	$stmt = $db->prepare('SELECT users.name, veranstaltung.vname, veranstaltung.datum, veranstaltung.startzeit, veranstaltung.endzeit FROM veranstaltung INNER JOIN users ON veranstaltung.dozent = users.idusers WHERE idveranstaltung = :id');
	$stmt->execute([':id' => $id]);
	$info = $stmt->fetch(PDO::FETCH_ASSOC);
	$stmt = $db->prepare('SELECT matrikelnummer, name, DATE(zeit), TIME(zeit), idanwesenheit FROM anwesenheit WHERE veranstaltung = :id');
	$stmt->execute([':id' => $id]);
	$anwesende = $stmt->fetchAll();
	$twig = $app['twig'];
	return $twig->render('studview.html.twig', ['info' => $info, 'anwesende' => $anwesende, 'id' => $id, 'matnr' => $_COOKIE["Matrikelnummer"], 'candelete' => $candelete]);
});

$app->post('/prof', function (Request $request) use ($app)
{
	$db = $app['database'];
	$login = $request
		->request
		->get('login');
	$passwort = $request
		->request
		->get('passwort');
	$passworthash = hash("sha256", $passwort);
	$userid = checkLogin($login, $passworthash, $app);
	if ($userid)
	{
		session_start();
		$_SESSION["login"] = $login;
		$_SESSION["passwort"] = $passworthash;
		$_SESSION["userid"] = $userid;
		return $app->redirect('/prof/' . $userid);
	}
	else
	{
		return $app->redirect('/prof');
	}
});

$app->post('/deletestud/{id}', function (Request $request, $id) use ($app)
{
	$db = $app['database'];
	$stmt = $db->prepare('SELECT dozent FROM veranstaltung WHERE idveranstaltung = :id');
	$stmt->execute([':id' => $id]);
	$dozentid = $stmt->fetch() [0];
	if (!checkSession($app, $dozentid))
	{
		return $app->redirect('/prof');
	};
	$anwid = $request
		->request
		->get('anwid');
	$stmt = $db->prepare('DELETE FROM anwesenheit WHERE idanwesenheit = :id');
	$stmt->execute([':id' => $anwid]);
	return $app->redirect('/' . $id);
});

$app->post('/deleteveranstaltung/{id}', function (Request $request, $id) use ($app)
{
	if (!checkSession($app, $id))
	{
		return $app->redirect('/prof');
	};
	$db = $app['database'];
	$vid = $request
		->request
		->get('vid');
	$stmt = $db->prepare('DELETE FROM veranstaltung WHERE idveranstaltung = :id');
	$stmt->execute([':id' => $vid]);
	return $app->redirect('/prof/' . $id);
});

$app->post('/changeveranstaltung/{id}', function (Request $request, $id) use ($app)
{
	$db = $app['database'];
	$stmt = $db->prepare('SELECT dozent FROM veranstaltung WHERE idveranstaltung = :id');
	$stmt->execute([':id' => $id]);
	$dozentid = $stmt->fetch() [0];
	if (!checkSession($app, $dozentid))
	{
		return $app->redirect('/prof');
	};
	$vname = $request
		->request
		->get('VName');
	$vdatum = $request
		->request
		->get('VDatum');
	$vstart = $request
		->request
		->get('VStart');
	$vende = $request
		->request
		->get('VEnde');
	$stmt = $db->prepare('UPDATE veranstaltung SET vname = :vname, datum = :vdatum, startzeit = :vstart, endzeit = :vende WHERE idveranstaltung = :id');
	$stmt->execute([':vname' => $vname, ':vdatum' => $vdatum, ':vstart' => $vstart, ':vende' => $vende, ':id' => $id]);
	return $app->redirect('/prof/' . $dozentid);
});

$app->post('/prof/{id}', function (Request $request, $id) use ($app)
{
	if (!checkSession($app, $id))
	{
		return $app->redirect('/prof');
	};
	$db = $app['database'];
	$vname = $request
		->request
		->get('VName');
	$vdatum = $request
		->request
		->get('VDatum');
	$vstart = $request
		->request
		->get('VStart');
	$vende = $request
		->request
		->get('VEnde');
	$vid = 0;
	do
	{
		$vid = randomNumber(6);
	}
	while (checkVeranstaltung($db, $vid));
	$stmt = $db->prepare('INSERT INTO veranstaltung (idveranstaltung, vname, dozent, datum, startzeit, endzeit) VALUES (:vid, :vname, :id, :vdatum, :vstart, :vende)');
	$stmt->execute([':vid' => $vid, ':vname' => $vname, ':id' => $id, ':vdatum' => $vdatum, ':vstart' => $vstart, ':vende' => $vende]);
	return $app->redirect('/prof/' . $id);
});

$app->post('/', function (Request $request) use ($app)
{
	$db = $app['database'];
	$redirect = "";
	$veranstaltungid = $request
		->request
		->get('veranstaltung');
	if (checkVeranstaltung($db, $veranstaltungid))
	{
		$redirect = $veranstaltungid;
	};
	return $app->redirect('/' . $redirect);
});

$app->post('/{id}', function (Request $request, $id) use ($app)
{
	$matnr = $request
		->request
		->get('MNr');
	$name = $request
		->request
		->get('StudName');
	setcookie("Matrikelnummer", $matnr);
	$db = $app['database'];
	$stmt = $db->prepare('INSERT INTO anwesenheit (matrikelnummer, name, veranstaltung) VALUES (:matnr, :name, :id)');
	$stmt->execute([':id' => $id, ':matnr' => $matnr, ':name' => $name]);
	return $app->redirect('/' . $id);
});

$app['database'] = function () use ($app)
{
	$dsn = getenv('MYSQL_DSN');
	$user = getenv('MYSQL_USER');
	$password = getenv('MYSQL_PASSWORD');
	if (!isset($dsn, $user) || false === $password)
	{
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
	if (empty($userid))
	{
		return "";
	}
	else
	{
		return $userid;
	}
}

function checkSession($app, $userid)
{
	session_start();
	$result = false;
	if ($_SESSION["userid"] == $userid)
	{
		$result = true;
	}
	return $result;
}

function randomNumber($length)
{
	$result = '';
	for ($i = 0;$i < $length;$i++)
	{
		$result .= mt_rand(0, 9);
	}
	return $result;
}

function checkVeranstaltung($db, $vid)
{
	$stmt = $db->prepare('SELECT EXISTS(SELECT * FROM veranstaltung WHERE idveranstaltung = :id)');
	$stmt->execute([':id' => $vid]);
	return $stmt->fetch() [0];
}

return $app;

