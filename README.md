# Micropub-Request for Kirby 3

 The Micropub `Request` class for Kirby 3 provides a simple API to inspect incoming [Micropub](https://www.w3.org/TR/micropub/) requests. It converts the submitted data, parses the properties of the request, and gets URL-referenced attachments. It maps the behavior of the `Kirby\Http\Request` object in many ways (Body, Files, Method, Auth), *except* for `url`, which contains the url of the page to be updated or deleted if applicable.

## Who is this for?
This is *not* a fully working Kirby 3 plugin. The goal of this code is to have a reliable foundation for developers working with Micropub and Kirby 3. You can easily add it as a dependency when developing your own Micropub server/endpoint plugin for Kirby.


## Features
- Single interface to all relevant parts of a Micropub request (auth, content, metadata, files)
- Consistent data, regardless of the syntax type [(JSON or x-www-form-urlencoded)](https://www.w3.org/TR/micropub/#h-syntax) of the request
- Processing of file attachments [referenced by URL](https://www.w3.org/TR/micropub/#uploading-files-p-5)
- Extracts `mp-` [server commands](https://indieweb.org/Micropub-extensions#Server_Commands) and [post status](https://indieweb.org/Micropub-extensions#Post_Status) from content fields
- IndieAuth access token verification with support for remote and local [token endpoints](https://indieweb.org/token-endpoint)
- Error handling

## Usage
Load the classes in `src/` using your favourite method, for example using [Kirby's built-in autoloader](https://getkirby.com/docs/reference/templates/helpers/load):

```php
load([
    'mof\\Micropub\\Request' => 'src/Request.php',
    'mof\\Micropub\\IndieAuth' => 'src/IndieAuth.php',
    'mof\\Micropub\\Error' => 'src/Error.php'
], __DIR__);
```


Then simply create a new object instance when your endpoint is called:

```php
$request = new \mof\Micropub\Request();
```


The micropub request maps the behavior of the `Kirby\Http\Request` object in many ways. If you have any experience with developing for Kirby, it should be instantly familiar, making the implementation of your own Micropub server a breeze:


```php
if($request->is('POST')) {

  // Makes it easy to implement 'create', 'update' or 'delete' actions for your endpoint
  switch($request->action()) {

    case 'create':

      // All the properties of the post, already standardized into a predictable data array
      $content = $request->body()->toArray();

      // Access the attachments, already downloaded and ready as Kirby\Http\Request\Files Object
      $files = $request->files();

      // Support for Micropub extensions like 'post-status'
      $status = $request->status();

    case 'update':

      // Url of the page to update available via url()
      $updatePage = $request->url();

     // etc.
```

### Content
Micropub clients can [send requests in various flavours](https://www.w3.org/TR/micropub/#h-overview). `$request->body()` provides an interface to the fully parsed ‘content-relevant’ fields, excluding command properties submitted by the client (for example `mp-slug` or references to attachments)— these are available through separate methods.


```php
$request->body();

 /*
  Kirby\Http\Request\Body Object
  (
    [name] => Simple blog post
    [content] => <b>Hello</b> World.
    [category] => Array
        (
            [0] => foo
            [1] => bar
        )

  )
*/
```

To get an array of the content fields use `$request->body()->toArray()`.

You can still access *all* properties submitted with the request using `$request->properties()->get()`, which returns a Mf2/Json-style array, regardless of the syntax the client actually used to submit the request:

```php
$request->properties()->get();

/*
	Array
	(
		[name] => Array
			(
				[0] => Array
					(
						[html] => Simple blog post
					)
			)
		[content] => Array
			(
				[0] => Array
					(
						[html] => <b>Hello</b> World.
					)
			)
		[category] => Array
			(
				[0] => foo
				[1] => bar
			)
		[mp-slug] => Array
			(
				[0] => hello-world
			)
		[post-status] => Array
			(
				[0] => draft
			)
	)
*/
```


### Files
[Files uploaded via multipart/form-data](https://www.w3.org/TR/micropub/#h-uploading-files), and/or files provided as URL value, are unified into a single [`Kirby\Http\Request\Files`](https://github.com/getkirby/kirby/blob/master/src/Http/Request/Files.php) object— Photo, video or audio attachments provided as URL will be fetched automatically.

```php
$request->files();

/*
	Kirby\Http\Request\Files Object
	(
		[photo] => Array
			(
				[0] => Array
					(
						[name] => 29B4C99C-1DF4-43B7-ARC4484587D4.jpg
						[type] => image/jpeg
						[tmp_name] => /private/var/tmp/phppfEuvz
						[error] => 0
						[size] => 428088
					)
			)
	)
*/
```


### IndieAuth Authorization
The bearer token submitted by the client as part of a Micropub request is automatically verified against a [token endpoint](https://indieweb.org/token-endpoint). The [verified access token](https://indieweb.org/token-endpoint#Verifying_an_Access_Token) returned by the token endoint is available via the ```auth()``` method:

```php
$request->auth();

 /*
	mof\Micropub\Request\IndieAuth Object
	(
		[type] => indie
		[me] => https://mydomain.tld
		[issued_by] => https://tokens.indieauth.com/token
		[client_id] => https://micropub.rocks/
		[issued_at] => 1525376494
		[scope] => Array
			(
				[0] => create
				[1] => update
				[2] => delete
			)
		[nonce] => 130510649
		[token] => *original bearer token submitted by the client*
	)
 */
```

Accessing individual properties:

```php
// Get an array of the scopes the token covers
$request->auth()->scope();

/*
	Array
	(
		[0] => create
		[1] => update
		[2] => delete
		[3] => undelete
		[4] => media
	)
*/
```

#### Remote verification
Per default, the ```IndieAuth``` class verifies a client-submitted token by making a remote request to the token endpoint [https://tokens.indieauth.com/token](https://tokens.indieauth.com/token). If you want to use another endpoint instead, set an option in Kirby's ```config.php```:

```php
'mof.micropub.auth.token-endpoint' => 'https://token.endpoint',
```

#### Local verification using your own token endpoint
The ```IndieAuth``` class will check for the existence of ```verifyMicropubAccessToken($bearer)``` before making a request to a remote token endpoint. This allows you to plug in your own token endpoint if you run it as part of your Kirby setup. Simply expose its functionality by declaring the function, for example in the token endpoint plugin's `index.php`:

```php
if (!function_exists('verifyMicropubAccessToken')) {
	function verifyMicropubAccessToken(string $bearer)
	{
		// Whatever your own token endpoint verification
		return $token = My\Token\Endpoint::verify($bearer);
	}
}
```

The `IndieAuth` class expects an object, for example a [`Kirby\Toolkit\Obj`](https://github.com/getkirby/kirby/blob/master/src/Toolkit/Obj.php) (a stdClass extension):

```php
$token = new \Kirby\Toolkit\Obj([
	'me' => $token->me(), // 'https://domain.tld'
	'client_id' => $token->client(), // 'https://micropub.rocks'
	'scope' => $token->scope(), // 'create update'
	'issued_at' => $token->time() // 1525376494
]);

$error = new \Kirby\Toolkit\Obj([
	'error' => 'forbidden',
	'error_description' => 'Token is invalid.'
]);
```

### Error Handling
If there are problems with parsing (parts of) data from the request, the object will include an error property accessible with `$request->error()`.

It is *highly* recommended you immediately check your request for errors after instantiating the object— In particular to address auth errors. (In any case, the request class will not parse the incoming data or attempt to download files if the auth token does not check out)

The included error handling class also allows you to send a response to the client, correctly formatted based on the HTTP accept header sent by the client:


```php
$request = new \mof\Micropub\Request();

// Catch all errors and return a response to the client
if ($request->error()) {
	return $request->error()->toErrorResponse();
}

/*
	HTTP/1.1 401 Unauthorized
	Content-Length: 72
	Content-Type: application/json; charset=UTF-8

	{
	  "error": "unauthorized",
	  "error_description": "No access token provided."
	}
*/
```

The error class is not limited to the Request object, it can come in handy anywhere when developing your Micropub server:

```php
// Create a new error object
$error = new \mof\Micropub\Error($error, $property, $description);

// Send an error message
\mof\Micropub\Error::response('insufficient_scope', $var, 'My custom message');
```


## Installation

### Download

Download and copy this repository to `/site/plugins/{{ your-plugin-name }}/vendor` and make sure to correctly load the classes, for example using [Kirby's built-in autoloader](https://getkirby.com/docs/reference/templates/helpers/load).


## Setup/Options
You can customize the following options in your `config.php`:

### Remote token endpoint
The default token endpoint used is [https://tokens.indieauth.com/token](https://tokens.indieauth.com/token). You can change it by setting

```php
'mof.micropub.auth.token-endpoint' => 'https://your.token.endpoint',
```

### Change ‘me’ URL path
As part of the authentication flow, the `IndieAuth` class will check the 'me' property of the received access token (the so called [User Profile URL](https://www.w3.org/TR/indieauth/#x3-1-user-profile-url)) against `kirby()->urls()->base()`. If you don't use `mydomain.tld` for IndieAuth, but for example `mydomain.tld/about/me`, you can set a path:

```php
'mof.micropub.me-path' => 'about/me',
```


## Reference

### `$request->action()`
Returns the client-requested action the Micropub server should perform as string. Defaults to `create`.

### `$request->auth()`
Returns a `mof\Micropub\Request\IndieAuth` object containing the verified access token (See documentation above). You should check whether the client has the necessary permissions to perform the requested action, so particularly `$request->auth()->scope()` is usefuel.

### `$request->body()`
Returns a [`Kirby\Http\Request\Body`](https://github.com/getkirby/kirby/blob/master/src/Http/Request/Body.php) object containing the parsed data (see documentation above). To simply get an array of all content fields use `$request->body()->toArray()`

### `$request->client()`
Returns the requesting client's ID (an URL) as string. Shortcut for `$request->auth()->client()`

### `$request->commands()`
Returns an array of the Micropub `mp-` [server commands](https://indieweb.org/Micropub-extensions#Server_Commands) included in the request.

### `$request->files()`
Returns a [`Kirby\Http\Request\Files`](https://github.com/getkirby/kirby/blob/master/src/Http/Request/Files.php) object constructed with all files which have been sent with the request (per form upload and/or as url reference, see documentation above).

### `$request->get(string $property, $fallback)`
Property getter. Example: `$request->get('files', false)`

### `$request->html()`
Returns an array listing all property fields which have been submitted as HTML. Useful if you want to convert your content to Markdown before saving it to a Kirby Page's content file.

### `$request->is(string $method)`
Checks if the given method name matches the name of the `$_SERVER['REQUEST_METHOD']`. Returns bool. Example: `$request->is('GET')`

### `$request->method()`
Returns the `$_SERVER['REQUEST_METHOD']` as string.

### `$request->properties()`
Returns a [`Kirby\Toolkit\Silo`](https://github.com/getkirby/kirby/blob/master/src/Toolkit/Silo.php) object containing the syntax-normalized properties of the request. Use `$request->properties()->get()` to get a Mf2/Json-style array of all Micropub properties submitted with the request. Other than the already parsed and converted `$request->body()->toArray()`, the properties array contains *all* properties submitted by the client (for example `mp-slug`), except for `type`.

### `$request->q()`
Shortcut for `$request->query()->get('q')`. This is useful when implementing [endpoint querying](https://www.w3.org/TR/micropub/#h-querying).

### `$request->query()`
Returns a [`Kirby\Http\Request\Query`](https://github.com/getkirby/kirby/blob/master/src/Http/Request/Query.php) object containing the parsed URL query.

### `$request->status()`
Returns the [post status](https://indieweb.org/Micropub-extensions#Post_Status) as string if submitted by the client, for example `draft` or `published`. This is a [Micropub extension](https://indieweb.org/Micropub-extensions) feature and not yet part of the formal W3C Recommendation.

### `$request->toArray()`
Converts the object to an array.

### `$request->toJson()`
Converts the object to a json string.

### `$request->toMf2()`
Returns the request as Mf2 array. Example:

```php
/*
	Array
	(
		[type] => h-entry
		[properties] => Array
			(
				[content] => Array
					(
						[0] => Hello, World
					)
				[mp-slug] => Array
					(
						[0] => hi-world
					)
			)
	)
*/
```

### `$request->type()`
Returns the microformats object type, i.e. the type of post which should be created (usually `entry`) of the request as string.

### `$request->update()`
Returns an array of field [update properties](https://www.w3.org/TR/micropub/#update) if submitted by the client (either `replace`, `add`, `delete` or any combination of these). Example:

```php
/*
	Array
	(
		[replace] => Array
			(
				[content] => Changed my mind.
			)
		[add] => Array
			(
				[title] => Great title
				[category] => bar
			)
		[delete] => Array
			(
				[category] => foo
			)
	)
*/
```

### `$request->url()`
Returns the URL of the page to update/delete as string. For performance reasons, this is just the plain URL and *not* already a Kirby page object, because you might have the need for a more specific query than simply checking against the `$kirby->site->index()` when looking for the page. Empty (obviously) if the 'action' is `create`


## Development
Please report any problems you encounter, as well as your thoughts and comments as [issues](https://github.com/moefuerst/kirby3-micropub-request/issues), or send a pull request!

## More Micropub for Kirby 3

- [kirby3-micropub-endpoint](https://github.com/moefuerst/kirby3-micropub-endpoint), a sample endpoint implementation using this class
- [kirby3-micropublisher](https://github.com/sebastiangreger/kirby3-micropublisher), a fully functioning Micropub endpoint plugin with a lot of customization options

## Credits
Inspiration and some code from
- [aaronpk/p3k-micropub](https://github.com/aaronpk/p3k-micropub)
- [sebsel/kirby-micropub](https://github.com/sebsel/kirby-micropub)
- [indieweb/wordpress-micropub](https://github.com/indieweb/wordpress-micropub)
- [sebsel/indieweb-toolkit](https://github.com/sebsel/indieweb-toolkit)
