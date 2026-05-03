<?php

namespace App\Form\Transformer;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

class UserToUsernameTransformer implements DataTransformerInterface
{
    public function __construct(
        private UserRepository $userRepository,
    ) {
    }

    public function transform($value): string
    {
        if (!$value instanceof User) {
            return '';
        }

        return $value->getUserIdentifier();
    }

    public function reverseTransform($value): ?User
    {
        $username = trim((string) $value);

        if ($username == '') {
            return null;
        }

        $user = $this->userRepository->findOneByUsername($username);

        if (!$user) {
            throw new TransformationFailedException('User not found.');
        }

        return $user;
    }
}
