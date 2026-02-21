<?php
declare(strict_types=1);

namespace MicroORM;


class QueryParams
{
    private ?int $page = null;
    private ?int $cant_by_page = null;

    private bool $enable_paging = false;
    private bool $enable_order = false;
    private array $order_fields = [];

    private ?string $pagination_replace_tag = null;
    private ?string $order_replace_tag = null;


    /**
     * @return string|null
     */
    public function getPaginationReplaceTag(): ?string
    {
        return $this->pagination_replace_tag;
    }

    /**
     * @param string $pagination_replace_tag
     */
    public function setPaginationReplaceTag(string $pagination_replace_tag)
    {
        $this->pagination_replace_tag = $pagination_replace_tag;
    }

    /**
     * @return string|null
     */
    public function getOrderReplaceTag(): ?string
    {
        return $this->order_replace_tag;
    }

    /**
     * @param string $order_replace_tag
     */
    public function setOrderReplaceTag(string $order_replace_tag)
    {
        $this->order_replace_tag = $order_replace_tag;
    }








    public function setEnablePaging(int $cant_by_page, int $page = 0): void
    {
        $this->enable_paging = true;
        $this->cant_by_page = $cant_by_page;
        $this->page = $page;
    }

    public function getPage(): ?int
    {
        return $this->page;
    }

    public function getCantByPage(): ?int
    {
        return $this->cant_by_page;
    }

    /**
     * @return bool
     */
    public function isEnablePaging(): bool
    {
        return $this->enable_paging;
    }

    /**
     * @return bool
     */
    public function isEnableOrder(): bool
    {
        return $this->enable_order;
    }



    public function addOrderField(string $field, bool $asc = true): void
    {
        if ($field !== '') {
            $this->order_fields[$field] = $asc;
            $this->enable_order = true;
        }
    }

    public function removeOrder(): void
    {
        $this->order_fields = [];
        $this->enable_order = false;
    }

    /**
     * @return array
     */
    public function getOrderFields(): array
    {
        if(is_null($this->order_fields)){
            $this->order_fields = array();
        }
        return $this->order_fields;
    }

    public function copyFrom(QueryParams $old): void
    {
        $this->page = $old->page;

        $this->cant_by_page = $old->cant_by_page;
        $this->enable_paging = $old->enable_paging;
        $this->enable_order = $old->enable_order;
        $this->order_fields = $old->order_fields;
    }
}