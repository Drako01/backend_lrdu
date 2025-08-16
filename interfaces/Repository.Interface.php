<?php
/**
 * @template T
 */
interface RepositoryInterface
{
    /** @return T|null */
    public function findById(int $id): ?object;

    /** @return T[] */
    public function findAll(): array;

    /** @param T $entity */
    public function save(object $entity): void;

    /** @param T $entity */
    public function update(object $entity): void;

    public function delete(int $id): void;
}
