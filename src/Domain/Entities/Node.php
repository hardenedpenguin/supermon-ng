<?php

declare(strict_types=1);

namespace SupermonNg\Domain\Entities;

use Ramsey\Uuid\UuidInterface;
use Ramsey\Uuid\Uuid;

class Node
{
    private UuidInterface $id;
    private string $nodeNumber;
    private string $callsign;
    private string $description;
    private string $location;
    private string $status;
    private ?string $lastHeard;
    private ?string $connectedNodes;
    private ?string $cosKeyed;
    private ?string $txKeyed;
    private ?string $cpuTemp;
    private ?string $alert;
    private ?string $wx;
    private ?string $disk;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        string $nodeNumber,
        string $callsign,
        string $description = '',
        string $location = '',
        ?UuidInterface $id = null
    ) {
        $this->id = $id ?? Uuid::uuid4();
        $this->nodeNumber = $nodeNumber;
        $this->callsign = $callsign;
        $this->description = $description;
        $this->location = $location;
        $this->status = 'unknown';
        $this->lastHeard = null;
        $this->connectedNodes = null;
        $this->cosKeyed = null;
        $this->txKeyed = null;
        $this->cpuTemp = null;
        $this->alert = null;
        $this->wx = null;
        $this->disk = null;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    // Getters
    public function getId(): UuidInterface
    {
        return $this->id;
    }

    public function getNodeNumber(): string
    {
        return $this->nodeNumber;
    }

    public function getCallsign(): string
    {
        return $this->callsign;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getLocation(): string
    {
        return $this->location;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getLastHeard(): ?string
    {
        return $this->lastHeard;
    }

    public function getConnectedNodes(): ?string
    {
        return $this->connectedNodes;
    }

    public function getCosKeyed(): ?string
    {
        return $this->cosKeyed;
    }

    public function getTxKeyed(): ?string
    {
        return $this->txKeyed;
    }

    public function getCpuTemp(): ?string
    {
        return $this->cpuTemp;
    }

    public function getAlert(): ?string
    {
        return $this->alert;
    }

    public function getWx(): ?string
    {
        return $this->wx;
    }

    public function getDisk(): ?string
    {
        return $this->disk;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    // Setters
    public function setStatus(string $status): void
    {
        $this->status = $status;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function setLastHeard(?string $lastHeard): void
    {
        $this->lastHeard = $lastHeard;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function setConnectedNodes(?string $connectedNodes): void
    {
        $this->connectedNodes = $connectedNodes;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function setCosKeyed(?string $cosKeyed): void
    {
        $this->cosKeyed = $cosKeyed;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function setTxKeyed(?string $txKeyed): void
    {
        $this->txKeyed = $txKeyed;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function setCpuTemp(?string $cpuTemp): void
    {
        $this->cpuTemp = $cpuTemp;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function setAlert(?string $alert): void
    {
        $this->alert = $alert;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function setWx(?string $wx): void
    {
        $this->wx = $wx;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function setDisk(?string $disk): void
    {
        $this->disk = $disk;
        $this->updatedAt = new \DateTimeImmutable();
    }

    // Business logic methods
    public function isOnline(): bool
    {
        return $this->status === 'online';
    }

    public function isKeyed(): bool
    {
        return $this->cosKeyed === '1' || $this->txKeyed === '1';
    }

    public function getCpuTempClass(): string
    {
        if (!$this->cpuTemp) {
            return 'unknown';
        }

        $temp = (float) $this->cpuTemp;
        
        if ($temp < 60) {
            return 'normal';
        } elseif ($temp < 80) {
            return 'warning';
        } else {
            return 'critical';
        }
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id->toString(),
            'node_number' => $this->nodeNumber,
            'callsign' => $this->callsign,
            'description' => $this->description,
            'location' => $this->location,
            'status' => $this->status,
            'last_heard' => $this->lastHeard,
            'connected_nodes' => $this->connectedNodes,
            'cos_keyed' => $this->cosKeyed,
            'tx_keyed' => $this->txKeyed,
            'cpu_temp' => $this->cpuTemp,
            'cpu_temp_class' => $this->getCpuTempClass(),
            'alert' => $this->alert,
            'wx' => $this->wx,
            'disk' => $this->disk,
            'is_online' => $this->isOnline(),
            'is_keyed' => $this->isKeyed(),
            'created_at' => $this->createdAt->format('c'),
            'updated_at' => $this->updatedAt->format('c'),
        ];
    }

    public static function fromArray(array $data): self
    {
        $node = new self(
            $data['node_number'],
            $data['callsign'],
            $data['description'] ?? '',
            $data['location'] ?? '',
            isset($data['id']) ? Uuid::fromString($data['id']) : null
        );

        if (isset($data['status'])) {
            $node->setStatus($data['status']);
        }
        if (isset($data['last_heard'])) {
            $node->setLastHeard($data['last_heard']);
        }
        if (isset($data['connected_nodes'])) {
            $node->setConnectedNodes($data['connected_nodes']);
        }
        if (isset($data['cos_keyed'])) {
            $node->setCosKeyed($data['cos_keyed']);
        }
        if (isset($data['tx_keyed'])) {
            $node->setTxKeyed($data['tx_keyed']);
        }
        if (isset($data['cpu_temp'])) {
            $node->setCpuTemp($data['cpu_temp']);
        }
        if (isset($data['alert'])) {
            $node->setAlert($data['alert']);
        }
        if (isset($data['wx'])) {
            $node->setWx($data['wx']);
        }
        if (isset($data['disk'])) {
            $node->setDisk($data['disk']);
        }

        return $node;
    }
}


