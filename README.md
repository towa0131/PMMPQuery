## MinecraftQuery
## Demo
```php
$query = new MinecraftQuery();
$query->connect("127.0.0.1", 19132);
if($query->isOnline()){
    $info = $query->getInfo();
    $players = $query->getPlayers();
    print_r($info);
    print_r($players);
}else{
    echo "Offline" . PHP_EOL;
}
```
