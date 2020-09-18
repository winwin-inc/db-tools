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
     * @CreationTimestamp
     *
     * @var \DateTime|null
     */
    private $createTime;
    /**
     * @UpdateTimestamp
     *
     * @var \DateTime|null
     */
    private $updateTime;
    /**
     * @var int|null
     */
    private $clientId;
    /**
     * @var string|null
     */
    private $vipNo;

    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @param int|null $id
     */
    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    /**
     * @return \DateTime|null
     */
    public function getCreateTime(): ?\DateTime
    {
        return $this->createTime;
    }

    /**
     * @param \DateTime|null $createTime
     */
    public function setCreateTime(?\DateTime $createTime): void
    {
        $this->createTime = $createTime;
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
     * @return string|null
     */
    public function getVipNo(): ?string
    {
        return $this->vipNo;
    }

    /**
     * @param string|null $vipNo
     */
    public function setVipNo(?string $vipNo): void
    {
        $this->vipNo = $vipNo;
    }
}
