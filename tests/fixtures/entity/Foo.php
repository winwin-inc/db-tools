<?php

declare(strict_types=1);

namespace winwin\db\tools\fixtures\entity;

use kuiper\db\annotation\CreationTimestamp;
use kuiper\db\annotation\GeneratedValue;
use kuiper\db\annotation\Id;
use kuiper\db\annotation\UpdateTimestamp;

class Foo
{
    /**
     * @Id
     * @GeneratedValue
     *
     * @var int|null
     */
    private $id;

    /**
     * @var int|null
     */
    private $clientId;
    /**
     * @UpdateTimestamp
     *
     * @var \DateTime|null
     */
    private $updateTime;

    /**
     * @CreationTimestamp
     *
     * @var \DateTime|null
     */
    private $createTime;

    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return \DateTime|null
     */
    public function getCreateTime(): ?\DateTime
    {
        return $this->createTime;
    }

    /**
     * @param int|null $id
     */
    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    /**
     * @param \DateTime|null $createTime
     */
    public function setCreateTime(?\DateTime $createTime): void
    {
        $this->createTime = $createTime;
    }

    /**
     * @return int|null
     */
    public function getClientId(): ?int
    {
        return $this->clientId;
    }

    /**
     * @param int|null $clientId
     */
    public function setClientId(?int $clientId): void
    {
        $this->clientId = $clientId;
    }

    /**
     * @return \DateTime|null
     */
    public function getUpdateTime(): ?\DateTime
    {
        return $this->updateTime;
    }

    /**
     * @param \DateTime|null $updateTime
     */
    public function setUpdateTime(?\DateTime $updateTime): void
    {
        $this->updateTime = $updateTime;
    }
}
