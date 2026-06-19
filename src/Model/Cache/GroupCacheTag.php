<?php

declare(strict_types=1);

namespace SR\SimpleProductLink\Model\Cache;

class GroupCacheTag
{
    private const TAG_PREFIX = 'sr_spl_group_';

    public function getTag(string $groupValue): string
    {
        return self::TAG_PREFIX . sha1($groupValue);
    }

    /**
     * @param mixed[] $groupValues
     * @return string[]
     */
    public function getTags(array $groupValues): array
    {
        $tags = [];
        foreach ($groupValues as $groupValue) {
            if (!$this->isValidGroupValue($groupValue)) {
                continue;
            }

            $tags[$this->getTag((string)$groupValue)] = true;
        }

        return array_keys($tags);
    }

    public function isValidGroupValue(mixed $groupValue): bool
    {
        return $groupValue !== null && $groupValue !== '';
    }
}
