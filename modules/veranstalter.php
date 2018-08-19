<?php

/**
 * Alle Seitenaufrufe, die Veranstalter betreffen.
 */
use Symfony\Component\HttpFoundation\Request;

//======================================================================
// DASHBOARD
//======================================================================

//-----------------------------------------------------
// Get-Requests

//Veranstalter Dashboard
$app->get('/veranstalter/{id}',
function ($id) use($app)
{
	if (!checkSession($app, $id, 'userid')) {
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
		$stmt = $db->prepare('SELECT * FROM termin WHERE veranstaltung = :id ORDER BY datum ASC, startzeit ASC');
		$stmt->execute([':id' => $veranstaltungen[$i]['idveranstaltung']]);
		$temp = $stmt->fetchAll();
		$veranstaltungen[$i]['termine'] = $temp;
	};
	$twig = $app['twig'];
	return $twig->render('profview.html.twig', ['name' => $name, 'veranstaltungen' => $veranstaltungen, 'id' => $id]);
});

//======================================================================
// VERANSTALTUNGEN
//======================================================================

//-----------------------------------------------------
// Get-Requests

//Anzeigen der Veranstaltungsübersicht
$app->get('/veranstaltung/{id}',
function ($id) use($app)
{
	$db = $app['database'];
	$stmt = $db->prepare('SELECT dozent FROM veranstaltung WHERE idveranstaltung = :id');
	$stmt->execute([':id' => $id]);
	$dozentid = $stmt->fetch() [0];
	if (!checkSession($app, $dozentid, 'userid')) {
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

//Seite zur Änderung der Daten einer Veranstaltung
$app->get('/changeveranstaltung/{id}',
function ($id) use($app)
{
	$db = $app['database'];
	$stmt = $db->prepare('SELECT dozent FROM veranstaltung WHERE idveranstaltung = :id');
	$stmt->execute([':id' => $id]);
	$dozentid = $stmt->fetch() [0];
	if (!checkSession($app, $dozentid, 'userid')) {
		return $app->redirect('/veranstalter');
	};
	$stmt = $db->prepare('SELECT * FROM veranstaltung WHERE idveranstaltung = :id');
	$stmt->execute([':id' => $id]);
	$vdata = $stmt->fetch(PDO::FETCH_ASSOC);
	$twig = $app['twig'];
	return $twig->render('changeveranstaltungview.html.twig', ['vdata' => $vdata]);
});

//-----------------------------------------------------
// Post-Requests

//Anlegen einer Veranstaltung
$app->post('/veranstalter/{id}',
function (Request $request, $id) use($app)
{
	if (!checkSession($app, $id, 'userid')) {
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

//Löschen einer Veranstaltung
$app->post('/deleteveranstaltung/{id}',
function (Request $request, $id) use($app)
{
	$pid = $request->request->get('pid');
	if (!checkSession($app, $pid, 'userid')) {
		return $app->redirect('/veranstalter');
	};
	$db = $app['database'];
	$stmt = $db->prepare('DELETE FROM veranstaltung WHERE idveranstaltung = :id');
	$stmt->execute([':id' => $id]);
	return $app->redirect('/veranstalter/' . $pid);
});

//Ändern von Veranstaltungsdaten
$app->post('/changeveranstaltung/{id}',
function (Request $request, $id) use($app)
{
	$db = $app['database'];
	$stmt = $db->prepare('SELECT dozent FROM veranstaltung WHERE idveranstaltung = :id');
	$stmt->execute([':id' => $id]);
	$dozentid = $stmt->fetch() [0];
	if (!checkSession($app, $dozentid, 'userid')) {
		return $app->redirect('/veranstalter');
	};
	$vname = $request->request->get('VName');
	$stmt = $db->prepare('UPDATE veranstaltung SET vname = :vname WHERE idveranstaltung = :id');
	$stmt->execute([':vname' => $vname, ':id' => $id]);
	return $app->redirect('/veranstalter/' . $dozentid);
});

//======================================================================
// TERMINE
//======================================================================

//-----------------------------------------------------
// Get-Requests

//Anzeigen der Terminübersicht (nur für Veranstalter)
$app->get('/termin/{id}',
function ($id) use($app)
{
	$db = $app['database'];
	$candelete = true;
	$dozentid = getDozentForTermin($db, $id);
	if (!checkSession($app, $dozentid, 'userid')) {
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
	$eintragen = checkAnmeldeZeitraum($eintragzeit, 900);
	$terminview = true;
	return $twig->render('studview.html.twig', ['info' => $info, 'anwesende' => $anwesende, 'id' => $id, 'matnr' => $_COOKIE["Matrikelnummer"], 'candelete' => $candelete, 'eintragen' => $eintragen, 'eintragzeit' => $eintragzeit, 'angemeldet' => $angemeldet, 'terminview' => $terminview]);
});

//Seite zur Änderung der Daten eines Termins
$app->get('/changetermin/{id}',
function ($id) use($app)
{
	$db = $app['database'];
	$dozentid = getDozentForTermin($db, $id);
	if (!checkSession($app, $dozentid, 'userid')) {
		return $app->redirect('/veranstalter');
	};
	$stmt = $db->prepare('SELECT * FROM termin WHERE idtermin = :id');
	$stmt->execute([':id' => $id]);
	$vdata = $stmt->fetch(PDO::FETCH_ASSOC);
	$twig = $app['twig'];
	return $twig->render('changeterminview.html.twig', ['vdata' => $vdata]);
});

//Manuelles starten des Anmeldetimers
$app->get('/starttimer/{id}',
function ($id) use($app)
{
	$db = $app['database'];
	$dozentid = getDozentForTermin($db, $id);
	if (!checkSession($app, $dozentid, 'userid')) {
		return $app->redirect('/veranstalter');
	};
	$stmt = $db->prepare('UPDATE termin SET eintragenab = FROM_UNIXTIME(:zeit), datum =DATE(FROM_UNIXTIME(:zeit)) WHERE idtermin = :id');
	$stmt->execute([':zeit' => time() , ':id' => $id]);
	return $app->redirect('/termin/' . $id);
});

//Manuelles beenden des Anmeldetimers
$app->get('/stoptimer/{id}',
function ($id) use($app)
{
	$db = $app['database'];
	$dozentid = getDozentForTermin($db, $id);
	if (!checkSession($app, $dozentid, 'userid')) {
		return $app->redirect('/veranstalter');
	};
	$stmt = $db->prepare('UPDATE termin SET eintragenab = FROM_UNIXTIME(:zeit) WHERE idtermin = :id');
	$stmt->execute([':zeit' => 0000000001, ':id' => $id]);
	return $app->redirect('/termin/' . $id);
});

//-----------------------------------------------------
// Post-Requests

//Hinzufügen eines Termins zu einer Veranstaltung
$app->post('/addtermin',
function (Request $request) use($app)
{
	$db = $app['database'];
	$pid = $request->request->get('pid');
	if (!checkSession($app, $pid, 'userid')) {
		return $app->redirect('/veranstalter');
	};
	$vid = $request->request->get('vid');
	$now = getdate();
	$tdatum = "" . $now['year'] . "-" . $now['mon'] . "-" . $now['mday'];
	$tstart = $request->request->get('TStart');
	$tende = $request->request->get('TEnde');
	$eintragen = new DateTime("" . $tdatum . " " . $tstart . ":00");
	$eintragen = date_timestamp_get($eintragen);
	$tid = 0;
	do {
		$tid = randomNumber(8);
	}
	while (checkTermin($db, $tid));
	$stmt = $db->prepare('INSERT INTO termin (idtermin, datum, startzeit, endzeit, veranstaltung, eintragenab) VALUES (:tid, :tdatum, :tstart, :tende, :vid, FROM_UNIXTIME(:eintragen))');
	$stmt->execute([':tid' => $tid, ':tdatum' => $tdatum, ':tstart' => $tstart, ':tende' => $tende, ':vid' => $vid, ':eintragen' => $eintragen]);
	return $app->redirect('/veranstalter/' . $pid);
});

//Löschen eines Termins
$app->post('/deletetermin/{id}',
function (Request $request, $id) use($app)
{
	$pid = $request->request->get('pid');
	if (!checkSession($app, $pid, 'userid')) {
		return $app->redirect('/veranstalter');
	};
	$db = $app['database'];
	$stmt = $db->prepare('DELETE FROM termin WHERE idtermin = :id');
	$stmt->execute([':id' => $id]);
	return $app->redirect('/veranstalter/' . $pid);
});

//Ändern von Termindaten
$app->post('/changetermin/{id}',
function (Request $request, $id) use($app)
{
	$db = $app['database'];
	$dozentid = getDozentForTermin($db, $id);
	if (!checkSession($app, $dozentid, 'userid')) {
		return $app->redirect('/veranstalter');
	};
	$vdatum = $request->request->get('VDatum');
	$vstart = $request->request->get('VStart');
	$vende = $request->request->get('VEnde');
	$stmt = $db->prepare('UPDATE termin SET datum = :vdatum, startzeit = :vstart, endzeit = :vende WHERE idtermin = :id');
	$stmt->execute([':vdatum' => $vdatum, ':vstart' => $vstart, ':vende' => $vende, ':id' => $id]);
	return $app->redirect('/veranstalter/' . $dozentid);
});

//Eintragen eines Teilnehmers im Termin durch Veranstalter
$app->post('/termin/{id}',
function (Request $request, $id) use($app)
{
	$db = $app['database'];
	$candelete = true;
	$dozentid = getDozentForTermin($db, $id);
	if (!checkSession($app, $dozentid, 'userid')) {
		return $app->redirect('/veranstalter');
	};

	$matnr = $request->request->get('MNr');
	$name = $request->request->get('StudName');
	
	$stmt = $db->prepare('INSERT INTO anwesenheit (matrikelnummer, name, termin, zeit) VALUES (:matnr, :name, :id, FROM_UNIXTIME(:zeit))');
	$stmt->execute([':id' => $id, ':matnr' => $matnr, ':name' => $name, ':zeit' => time() ]);


	return $app->redirect('/termin/' . $id);
});

//Löschen einer Eintragung eines Termins
$app->post('/deletestud/{id}',
function (Request $request, $id) use($app)
{
	$db = $app['database'];
	$dozentid = getDozentForTermin($db, $id);
	if (!checkSession($app, $dozentid, 'userid')) {
		return $app->redirect('/veranstalter');
	};
	$anwid = $request->request->get('anwid');
	$stmt = $db->prepare('DELETE FROM anwesenheit WHERE idanwesenheit = :id');
	$stmt->execute([':id' => $anwid]);
	return $app->redirect('/termin/' . $id);
});