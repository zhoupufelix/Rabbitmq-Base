<?php
/**
 * rabbitmq 基础类库
 * credit by felix
 */


class RabbitMq{

  static private $_instance = null ;//私有的静态实例
  static private $_conn;//私有的连接
  static private $routing_key;//routing key
  static private $q ;//队列实例
  static private $ex ;//交换机实例
  static private $queue;//队列名



  /**
   * 公开的方法获取实例
   * @return [type] [description]
   */
  public static function getInstance(){
    $config = include(__DIR__.'/../config/rabbitmq.php');
    if (self::$_instance == null) {
          self::$_instance = new self($config);
          return self::$_instance;
    }
    return self::$_instance;
  }

  /**
   * 私有的构造方法 防止类被实例化 违背单例原则
   * @param [type] $config 配置信息
   */
  private function __construct($config)
  {
      //创建连接和channel
      $conn = new AMQPConnection($config);
      if( !$conn->connect() ) {
          die("Cannot connect to the broker!\n");
      }
      self::$_conn = new AMQPChannel($conn);//获得broker 连接
  }

   /**
    * 设置交换机
    * @param [type] $params 交换机参数 $params
    * [
    *   'exchange_name'=>'',
    *   'exchange_type'=>AMQP_EX_TYPE_DIRECT, AMQP_EX_TYPE_FANOUT, AMQP_EX_TYPE_HEADER or AMQP_EX_TYPE_TOPIC',默认AMQP_EX_TYPE_DIRECT,
    *   'exchange_arguments'=>[]
    * ]
    * @return $instance
    */
   public function setExchange(array $params){
       if (  !isset($params['exchange_name']) || empty($params['exchange_name'])  ) {
        die('no exchange name!');
       }
       if (  !isset($params['routing_key']) || empty($params['routing_key'])  ) {
          die('no routing-key!');
       }
       //创建交换机
       $ex = new AMQPExchange(self::$_conn);
       self::$ex = $ex;
       $ex->setName($params['exchange_name']);
       $type = isset( $params['exchange_type'] ) && !empty( $params['exchange_type'] ) ? $params['exchange_type'] : AMQP_EX_TYPE_DIRECT;//direct类型
       $ex->setType($type);
       $ex->setFlags(AMQP_DURABLE); //持久化
       if ( isset($params['exchange_arguments']  ) && !empty( $params['exchange_arguments'] ) ) {
         $ex->setArguments ($params['exchange_arguments']);
       }
       //定义交换机 no warning
       $ex->declareExchange();
       self::$routing_key = $params['routing_key'];
       return self::$_instance;//返回对象 链式
   }

   /**
    * 创建队列并设置参数
    * @param [type] $params 队列参数 $params
    * [
    *   'queue_name'=>'',
    *   'queue_arguments'=>array(
    *         'x-dead-letter-exchange'    => 'delay_exchange',
    *         'x-dead-letter-routing-key' => 'delay_route',
    *         'x-message-ttl'             => 60000,
    *     )
    * ]
    * @return $instance
    */
   public  function setQueue(array $params){
      if (  !isset($params['queue_name']) || empty($params['queue_name'])  ) {
          die('no queue name!');
      }
      if (  !isset($params['routing_key']) || empty($params['routing_key'])  ) {
          die('no routing-key!');
      }
      //创建队列
      $q = new AMQPQueue(self::$_conn);
      $q->setName($params['queue_name']);
      $q->setFlags(AMQP_DURABLE);
      if ( isset($params['queue_arguments']  ) && !empty( $params['queue_arguments'] ) ) {
        $ex->setArguments ($params['queue_arguments']);
      }
      $q->declareQueue();
      //绑定队列到交换机
      $q->bind($params['exchange_name'],  $params['routing_key']);
      self::$q = $q;
      return self::$_instance;//返回对象 链式
  }

  /*
  * 消费者
  * $fun_name = array($classobj,$function) or function name string
  * $autoack 是否自动应答
  *
  * function processMessage($envelope, $queue) {
  *    $msg = $envelope->getBody();
  *    echo $msg."\n"; //处理消息
  *    $queue->ack($envelope->getDeliveryTag());//手动应答
  *}
  * callback 阻塞接受
  */
  public function run($func, $autoack = True){
      if ( !$func || !self::$q ) return False;
      if ( $autoack ) {
        self::$q->consume($func,AMQP_AUTOACK);
      }else{
        self::$q->consume($func);
      }

  }

  /**
   * 非阻塞获取消息
   * @return [type] [description]
   */
  public function get($autoack = True){
    if ( $autoack ) {
      $message = self::$q->get(AMQP_AUTOACK);
      return !empty($message) ? $message->getBody() : '';
    }

    $message = self::$q->get();
    if (  !empty($message)  )
    {
      // $queue->ack($message->getDeliveryTag());    //应答，代表该消息已经消费
      return $message->getBody();
    }

    return '';
  }


   /**
    * 关闭rabbitmq连接
    * @return [type] [description]
    */
   public function closeConn(){
     self::$_conn->disconnect();
   }


   /**
    * 生产者
    * @param  [type] $msg [description]
    * @return [type]      [description]
    */
   public function publish($msg,$flags=AMQP_NOPARAM ,$attributes=[]){
     return  self::$ex->publish($msg,self::$routing_key,$flags ,$attributes);
   }

  /**
   * __clone方法防止对象被复制克隆
   * @return [type] [description]
   */
  public function __clone()
  {
      trigger_error('Clone is not allow!', E_USER_ERROR);
  }

}

//生产者
// $params=[
//         'exchange_name'=>'exchange1',
//         'routing_key'=>'routing_key1',
//         'queue_name'=>'queue1',
// ];
// RabbitMq::getInstance()->setExchange($params)->publish('msg');//default direct
//
//消费者 阻塞
//  RabbitMq::getInstance()->setQueue($params)->run(function($envelope, $queue) {
//         $msg = $envelope->getBody();
//         echo $msg."\n"; //处理消息
//         // $queue->ack($envelope->getDeliveryTag());//手动ack
//      });
//
//
//
//
