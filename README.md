# puxt


## Setup
Create pages folder and add index.php file
```php
use Laminas\Diactoros\Response\HtmlResponse;
return new class{
    public function get(){
        return new HtmlResponse("Hello world");
    }
}
```

It will output `Hello world` in the browser when reqeust to `/` path.



## Debug
Set the `DEBUG` environment variable to `true` to enable debug mode.
```env
DEBUG=true
```

## Exception format
Set the `DEBUG_EXCEPTION_FORMAT` environment variable to `json` to enable exception format.
```env
DEBUG_EXCEPTION_FORMAT=json
```

## Base path of uri    
Set the `BASE_PATH` environment variable to change the base path.
```env


## Route strategy
Set the `ROUTE_STRATEGY` environment variable to change the route strategy.
```env
ROUTE_STRATEGY=json
```

### HTML header
It will change the html title to `Custom title`.
```php
use function PUXT\useHead;

//call this function in the get method of the page
useHead([
    "title" => "Custom title",
]);
```

