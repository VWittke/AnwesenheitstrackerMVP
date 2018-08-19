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
date_default_timezone_set('Europe/Berlin');

//======================================================================
// Routen
//======================================================================

require_once __DIR__ . '/modules/signup.php';
require_once __DIR__ . '/modules/admin.php';
require_once __DIR__ . '/modules/veranstalter.php';
require_once __DIR__ . '/modules/registeredUsers.php';
require_once __DIR__ . '/modules/general.php';

//======================================================================
// Hilfsfunktionen
//======================================================================

//Datenbankanbindung
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

//Überprüfen des Logins
function checkLogin($login, $passwort, $app, $admin)
{
	$db = $app['database'];
	$stmt = $db->prepare('SELECT idusers FROM users WHERE login = :login AND passwort = :passwort');
	$stmt->execute([':login' => $login, ':passwort' => $passwort]);
	$userid = $stmt->fetchColumn();
	$stmt = $db->prepare('SELECT isadmin FROM users WHERE idusers = :id');
	$stmt->execute([':id' => $userid]);
	$isadmin = $stmt->fetchColumn();
	if (empty($userid) || ($isadmin != $admin)) {
		return "";
	}
	else {
		return $userid;
	}
}

//Überprüfen ob gültige Session für Aktion
function checkSession($app, $userid, $token)
{
	session_start();
	$result = false;
	if ($_SESSION["" . $token] == $userid) {
		$result = true;
	}
	return $result;
}

//Generieren einer Zufallszahl für IDs
function randomNumber($length)
{
	$result = '';
	for ($i = 0; $i < $length; $i++) {
		$result.= mt_rand(0, 9);
	}

	return $result;
}

//Dozent für Termin Abfragen
function getDozentForTermin($db, $did) {
	$stmt = $db->prepare('SELECT veranstaltung.dozent FROM veranstaltung INNER JOIN termin ON veranstaltung.idveranstaltung = termin.veranstaltung WHERE termin.idtermin = :id');
	$stmt->execute([':id' => $did]);
	$dozentid = $stmt->fetch() [0];
	return $dozentid;
}

//Überprüfen, ob Termin existiert
function checkTermin($db, $tid)
{
	$stmt = $db->prepare('SELECT EXISTS(SELECT * FROM termin WHERE idtermin = :id)');
	$stmt->execute([':id' => $tid]);
	return $stmt->fetch() [0];
}

//Überprüfen, ob Veranstaltung existiert
function checkVeranstaltung($db, $vid)
{
	$stmt = $db->prepare('SELECT EXISTS(SELECT * FROM veranstaltung WHERE idveranstaltung = :id)');
	$stmt->execute([':id' => $vid]);
	return $stmt->fetch() [0];
}

//Überprüfen, ob aktuell Anmeldung möglich
function checkAnmeldeZeitraum($eintragstart, $timelength) 
{
	$distance = time() - $eintragstart;
	$eintragen = false;
	if (($distance >= 0) && ($distance <= $timelength)) {
		$eintragen = true;
	}
	return $eintragen;
}

//Überprüfen, ob Termin valide ist
function checkVeranstaltungValid($db, $vid)
{
	$stmt = $db->prepare('SELECT eintragenab FROM termin WHERE veranstaltung = :id AND datum < :maxdatum ORDER BY datum DESC, startzeit DESC');
	$stmt->execute([':id' => $vid, ':maxdatum' => "9999-12-31"]);
	$eintragzeit = $stmt->fetch() [0];
	$eintragzeit = strtotime($eintragzeit);
	$eintragen = checkAnmeldeZeitraum($eintragzeit, 900);
	return $eintragen;
}

return $app;

