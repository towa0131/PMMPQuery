## MinecraftQuery
## How to use?
```php
$query = new MinecraftQuery();
$query->connect("127.0.0.1",19132); if($query->isOnline())
{
 $info = $query->getInfo();
 print_r($info);
}
```