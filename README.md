## MinecraftQuery
## How to use?
    $query = new MinecraftQuery();
    //IP and Port
    $query->Connect("127.0.0.1",19132);
    x
    if($query->isOnline()){
     $info = $query->GetInfo();
     echo $info["HostPort"];
     //19132
    }