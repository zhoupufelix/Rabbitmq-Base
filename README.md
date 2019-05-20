# Rabbitmq-Base
RabbitMq Base Class BY PHP

## How To Use?

### Publisher

```PHP
$params=[
         'exchange_name'=>'exchange1',
         'routing_key'=>'routing_key1',
         'queue_name'=>'queue1',
 ];
 RabbitMq::getInstance()->setExchange($params)->publish('msg');//default direct
```


### Consumer (function consume)

```PHP
$params=[
         'exchange_name'=>'exchange1',
         'routing_key'=>'routing_key1',
         'queue_name'=>'queue1',
 ];
 RabbitMq::getInstance()->setQueue($params)->run(function($envelope, $queue) {
   $msg = $envelope->getBody();
   echo $msg."\n"; //处理消息
   $queue->ack($envelope->getDeliveryTag());//手动ack
    sleep(601);
},false);
```
