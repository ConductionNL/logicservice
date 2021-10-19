<?php

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use App\Repository\PersonRepository;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ApiResource(
 *     normalizationContext={"groups"={"read"}, "enable_max_depth"=true},
 *     denormalizationContext={"groups"={"write"}, "enable_max_depth"=true},
 *     collectionOperations={
 *      "post"
 *     },
 *     itemOperations={
 *       "get"
 *     }
 * )
 * @ORM\Entity(repositoryClass=PersonRepository::class)
 */
class Person
{
    /**
     * @var UuidInterface
     *
     * @example e2984465-190a-4562-829e-a8cca81aa35d
     *
     * @Groups({"read"})
     * @ORM\Id
     * @ORM\Column(type="uuid", unique=true)
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class="Ramsey\Uuid\Doctrine\UuidGenerator")
     */
    private $id;

    /**
     *
     * @Groups({"read"})
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $isEligible;

    /**
     *
     * @Groups({"read"})
     * @ORM\Column(type="array", nullable=true)
     */
    private $coMovers = [];

    /**
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="string", length=255)
     */
    private $brp;

    public function getId(): ?UuidInterface
    {
        return $this->id;
    }

    public function getIsEligible(): ?bool
    {
        return $this->isEligible;
    }

    public function setIsEligible(bool $isEligible): self
    {
        $this->isEligible = $isEligible;

        return $this;
    }

    public function getCoMovers(): ?array
    {
        return $this->coMovers;
    }

    public function setCoMovers(?array $coMovers): self
    {
        $this->coMovers = $coMovers;

        return $this;
    }

    public function getBrp(): ?string
    {
        return $this->brp;
    }

    public function setBrp(string $brp): self
    {
        $this->brp = $brp;

        return $this;
    }
}
