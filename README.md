### HttpBatchBundle
HttpBatchBundle is a plugin for symfony ,that implement multipart/batch method.
### What is multipart/batch
Http multipart/batch is a format for packaging multiple HTTP requests in a single request. You can read this draft more info: https://tools.ietf.org/id/draft-snell-http-batch-00.html

###### You can decrease you request count (especially on api) with HTTPBatchBundle.
### Installing HttpBatchBundle
The easiest way to install HttpBatchBundle is through composer.
```bash
composer require mstfleri/http-batch-bundle
```
Do not forget register to AppKernel.php
```php
$bundles = [
            ...
            new \Hezarfen\HttpBatchBundle\HttpBatchBundle()
        ];
```
Now lets configurate it!
### Configuration
##### Routing
Add a route for HttpBatchBundle like that to your routing.yml
```yml
http_batch:
    resource: "@HttpBatchBundle/Controller/"
    type:     annotation
```
#### Sevice Registration
Register HttpBatchBundle services. Add this line to your services.yml
```yml
imports:
    ...
    - { resource: "@HttpBatchBundle/Resources/config/services.yml" }
```
That's all. Now you can use http batch implementation on your symfony project.
You can test it with Postman or anything else.

### If yo need a multipart/batch client?
You're lucky! You can try
https://github.com/mustafaileri/http-batch-client
