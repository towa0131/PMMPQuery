## MinecraftQuery
## How to use?
```php
$query = new MinecraftQuery();
//IP and Port
$query->Connect("127.0.0.1",19132); if($query->isOnline()){
 $info = $query->GetInfo();
 echo $info["HostPort"];
 //19132
}
```