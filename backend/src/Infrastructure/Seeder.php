<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Entity\Product;
use App\Entity\ProductAttribute;
use App\Entity\ProductImage;
use App\Entity\User;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use App\Settings;
use App\Storage\Storage;
use Doctrine\ORM\EntityManagerInterface;

final class Seeder
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $users,
        private readonly ProductRepository $products,
        private readonly Storage $storage,
        private readonly Settings $settings,
    ) {
    }

    public function ensureAdmin(): void
    {
        if ($this->users->findByEmail($this->settings->adminEmail) !== null) {
            return;
        }
        $this->users->add(new User(
            $this->settings->adminEmail,
            password_hash($this->settings->adminPassword, PASSWORD_DEFAULT),
            $this->settings->adminName,
        ));
        $this->em->flush();
    }

    public function seedCatalog(): int
    {
        $relative = 'images/seed/pixel.png';
        $absolute = $this->storage->absolute($relative);
        if (!is_file($absolute)) {
            file_put_contents(
                $absolute,
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true),
            );
        }

        $fixtures = [
            ['seed-bermuda', 'Бермуды мужские, Grigio, OMSA', 'Домашние бермуды из хлопка.', '1320.00', '33.33', ['Размер' => '46(M)', 'Бренд' => 'OMSA']],
            ['seed-leggings', 'Легинсы женские, Fumo, MINIMI', 'Эластичные легинсы с высокой талией.', '900.00', '33.33', ['Размер' => '42 (XS)', 'Цвет' => 'Fumo']],
            ['seed-bands', 'Бандалетки жен., Nero, MINIMI', 'Аксессуар на силиконовой основе.', '780.00', '33.33', ['Состав' => '86% Полиамид, 14% Эластан']],
        ];
        $created = 0;
        foreach ($fixtures as $fixture) {
            if ($this->products->findByExternalCode($fixture[0]) !== null) {
                continue;
            }
            $product = new Product($fixture[0], $fixture[1], $fixture[2], $fixture[3], $fixture[4]);
            foreach ($fixture[5] as $key => $value) {
                $product->addAttribute(new ProductAttribute($key, $value));
            }
            $product->addImage(new ProductImage('https://example.com/' . $fixture[0] . '.png', $relative, 0));
            $this->products->add($product);
            ++$created;
        }
        $this->em->flush();

        return $created;
    }
}
