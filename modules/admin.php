<?php

/**
 * Alle Seitenaufrufe, die den Admin betreffen.
 */
use Symfony\Component\HttpFoundation\Request;

//======================================================================
// DASHBOARD
//======================================================================

//-----------------------------------------------------
// Get-Requests

//Admin Dashboard
$app->get('/admin/{id}',
function ($id) use($app)
{
	if (!checkSession($app, $id, 'Adminuserid')) {
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

//======================================================================
// USERVEREWALTUNG
//======================================================================

//-----------------------------------------------------
// Get-Requests

//Seite zur Änderung von Userdaten
$app->get('/changeuser/{id}',
function ($id) use($app)
{
	$db = $app['database'];
	if (!checkSession($app, "3", 'Adminuserid')) {
		return $app->redirect('/admin');
	};
	$stmt = $db->prepare('SELECT * FROM users WHERE idusers = :id');
	$stmt->execute([':id' => $id]);
	$udata = $stmt->fetch(PDO::FETCH_ASSOC);
	$twig = $app['twig'];
	return $twig->render('changeuserview.html.twig', ['udata' => $udata]);
});

//-----------------------------------------------------
// Post-Requests

//Anlegen eines Users durch Admin
$app->post('/admin/{id}',
function (Request $request, $id) use($app)
{
	if (!checkSession($app, $id, 'Adminuserid')) {
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

//Löschen eines Benutzers durch Admin
$app->post('/deleteuser/{id}',
function (Request $request, $id) use($app)
{
	$pid = $request->request->get('pid');
	if (!checkSession($app, $pid, 'Adminuserid')) {
		return $app->redirect('/admin');
	};
	$db = $app['database'];
	$stmt = $db->prepare('DELETE FROM users WHERE idusers = :id');
	$stmt->execute([':id' => $id]);
	return $app->redirect('/admin/' . $pid);
});

//Ändern von Userdaten
$app->post('/changeuser/{id}',
function (Request $request, $id) use($app)
{
	$db = $app['database'];
	if (!checkSession($app, 3, 'Adminuserid')) {
		return $app->redirect('/admin');
	};
	$dlogin = $request->request->get('DLogin');
	$dpass = $request->request->get('DPass');
	$dname = $request->request->get('DName');
	$stmt = $db->prepare('UPDATE users SET login = :login, passwort = :passwort, name = :name WHERE idusers = :id');
	$stmt->execute([':login' => $dlogin, ':passwort' => hash("sha256", $dpass) , ':name' => $dname, ':id' => $id]);
	return $app->redirect('/admin/' . "3");
});