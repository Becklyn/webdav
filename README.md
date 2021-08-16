# Becklyn WebDav

A simple WebDav client.

## Installation

```
$ composer require becklyn/webdav
```

## Usage

```
$client = new \Becklyn\WebDav\Client(new \Becklyn\WebDav\Config(
    'base_url',
    'username',
    'password'
));

$files = $client->listFolderContents('path/to/folder');

foreach ($files as $file) {
    if ($file instanceof \Becklyn\WebDav\Resource\File) {
        file_put_contents("/tmp/{$file->path()}", $client->getFileContents($file));    
    }
}
```