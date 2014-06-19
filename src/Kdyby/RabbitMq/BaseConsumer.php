<?php

namespace Kdyby\RabbitMq;

use Nette\Utils\Callback;



abstract class BaseConsumer extends AmqpMember
{

	/**
	 * @var int
	 */
	protected $target;

	/**
	 * @var int
	 */
	protected $consumed = 0;

	/**
	 * @var callable
	 */
	protected $callback;

	/**
	 * @var int
	 */
	protected $forceStop = false;

	/**
	 * @var int
	 */
	protected $idleTimeout = 0;



	public function setCallback($callback)
	{
		Callback::check($callback);
		$this->callback = $callback;
	}



	public function start($msgAmount = 0)
	{
		$this->target = $msgAmount;

		$this->setupConsumer();

		while (count($this->getChannel()->callbacks)) {
			$this->getChannel()->wait();
		}
	}



	public function stopConsuming()
	{
		$this->getChannel()->basic_cancel($this->getConsumerTag());
	}



	protected function setupConsumer()
	{
		if ($this->autoSetupFabric) {
			$this->setupFabric();
		}

		$this->getChannel()->basic_consume(
			$this->queueOptions['name'],
			$this->getConsumerTag(),
			$noLocal = false,
			$noAck = false,
			$exclusive = false,
			$nowait = false,
			array($this, 'processMessage')
		);
	}



	protected function maybeStopConsumer()
	{
		if (extension_loaded('pcntl') && (defined('AMQP_WITHOUT_SIGNALS') ? !AMQP_WITHOUT_SIGNALS : true)) {
			if (!function_exists('pcntl_signal_dispatch')) {
				throw new \BadFunctionCallException("Function 'pcntl_signal_dispatch' is referenced in the php.ini 'disable_functions' and can't be called.");
			}

			pcntl_signal_dispatch();
		}

		if ($this->forceStop || ($this->consumed == $this->target && $this->target > 0)) {
			$this->stopConsuming();

		} else {
			return;
		}
	}



	public function setConsumerTag($tag)
	{
		$this->consumerTag = $tag;
	}



	public function getConsumerTag()
	{
		return $this->consumerTag;
	}



	public function forceStopConsumer()
	{
		$this->forceStop = true;
	}



	/**
	 * Sets the qos settings for the current channel
	 * Consider that prefetchSize and global do not work with rabbitMQ version <= 8.0
	 *
	 * @param int $prefetchSize
	 * @param int $prefetchCount
	 * @param bool $global
	 */
	public function setQosOptions($prefetchSize = 0, $prefetchCount = 0, $global = false)
	{
		$this->getChannel()->basic_qos($prefetchSize, $prefetchCount, $global);
	}



	public function setIdleTimeout($seconds)
	{
		$this->idleTimeout = $seconds;
	}



	public function getIdleTimeout()
	{
		return $this->idleTimeout;
	}

}