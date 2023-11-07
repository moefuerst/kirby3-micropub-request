<?php
/*
 * Micropub Request for Kirby 3 example
 *
 * Put this directory in a kirby installations' root folder
 * and run example.php in your browser
 */

// Bootstrap Kirby from parent directory
require dirname(__DIR__, 1) . '/kirby/bootstrap.php';
$kirby = new Kirby();

load([
    'mof\\Micropub\\Request' => 'lib/Request.php',
    'mof\\Micropub\\IndieAuth' => 'lib/IndieAuth.php',
    'mof\\Micropub\\Error' => 'lib/Error.php'
], __DIR__);

/*
// Uncomment this to fake verify any access token
function verifyMicropubAccessToken(string $bearer)
{
    echo 'Creating fake access token for bearer: ' . $bearer;
    return new \Kirby\Toolkit\Obj([
	    'me' => kirby()->urls()->base(),
	    'client_id' => 'https://micropub.rocks',
	    'scope' => 'create update media',
	    'issued_at' => time()
    ]);
}
*/

if (!empty($_POST)) {

    $request = new \mof\Micropub\Request();

    if ($request->error()) {
	    var_dump($request->error());
    }

    var_dump($request);
    exit();
}

else { ?>

<form action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
    <p>Test Micropub Request:</p>
    <input type="submit">
    <input type="hidden" name="h" value="entry">
    <input type="hidden" name="content" value="Hello World">
    <input type="hidden" name="slug" value="testslug">
    <input type="hidden" name="category[]" value="foo">
    <input type="hidden" name="category[]" value="bar">
    <input type="hidden" name="access_token" value="123456">
</form>

<?php } ?>
