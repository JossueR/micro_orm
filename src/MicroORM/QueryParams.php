<?php


namespace MicroORM;


class QueryParams
{
    private $page;
    private $cant_by_page;

    private $enable_paging = false;
    private $enable_order = false;
    /**
     * @var array
     */
    private $order_fields;

    /**
     * @var string
     */
    private $pagination_replace_tag;
    /**
     * @var string
     */
    private $order_replace_tag;


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








    /**
     * @param $cant_by_page
     * @param int $page
     */
    public function setEnablePaging($cant_by_page, int $page=0)
    {
        $this->enable_paging = true;
        $this->cant_by_page = $cant_by_page;
        $this->page = $page;
    }

    /**
     * @return int
     */
    public function getPage(): int
    {
        return $this->page;
    }

    /**
     * @return int
     */
    public function getCantByPage(): int
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



    public function addOrderField($field, $asc=true){

        if($field && $field != ''){
            $this->order_fields[$field] = $asc;
        }

    }

    public function removeOrder(){
        $this->order_fields = array();
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

    public function copyFrom(QueryParams $old){
        $this->page = $old->page;

        $this->cant_by_page = $old->cant_by_page;
        $this->enable_paging=$old->enable_paging;
        $this->enable_order = $old->enable_order;
        $this->order_fields = $old->order_fields;
    }
}