<?php

/**
 * Alle Seitenaufrufe, die jegliche Art von registrierten Usern betreffen.
 */
use Symfony\Component\HttpFoundation\Request;

//======================================================================
// EINLOGGEN
//======================================================================

//-----------------------------------------------------
// Get-Requests

//Veranstalter Login
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

//-----------------------------------------------------
// Post-Requests

//Einloggen als Veranstalter oder Admin
$app->post('/veranstalter',
function (Request $request) use($app)
{
	$db = $app['database'];
	$login = $request->request->get('login');
	$passwort = $request->request->get('passwort');
	$passworthash = hash("sha256", $passwort);
	$userid = checkLogin($login, $passworthash, $app, false);
	if ($userid) {
		session_start();
		$_SESSION["login"] = $login;
		$_SESSION["userid"] = $userid;
		return $app->redirect('/veranstalter/' . $userid);
	}
	else {
		$adminid = checkLogin($login, $passworthash, $app, true);
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

//======================================================================
// AUSLOGGEN
//======================================================================

//-----------------------------------------------------
// Get-Requests

//Logout
$app->get('/logout',
function () use($app)
{
	session_start();
	session_unset();
	session_destroy();
	return $app->redirect('/veranstalter');
});