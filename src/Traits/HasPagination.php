<?php

namespace Diffyne\Traits;

use Diffyne\Attributes\Invokable;
use Diffyne\Attributes\Locked;
use Diffyne\Attributes\QueryString;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;

/**
 * Trait for adding pagination functionality to Diffyne components.
 * Works seamlessly with Laravel's paginator
 */
trait HasPagination
{
    /**
     * Current page number (1-indexed).
     */
    #[QueryString(keep: true, as: 'page')]
    public int $page = 1;

    /**
     * Number of items per page.
     */
    public int $perPage = 15;

    /**
     * Whether the current page is the first page.
     */
    public bool $isFirstPage = true;

    /**
     * Whether the current page is the last page.
     */
    public bool $isLastPage = false;

    /**
     * Go to next page.
     */
    #[Invokable]
    public function nextPage(): void
    {
        $paginator = $this->getPaginator();
        if ($paginator && $paginator->hasMorePages()) {
            $this->page++;
            $this->onPageChange();
        } elseif (! $paginator && $this->hasMorePages()) {
            $this->page++;
            $this->onPageChange();
        }
    }

    /**
     * Go to previous page.
     */
    #[Invokable]
    public function previousPage(): void
    {
        $paginator = $this->getPaginator();
        if ($paginator && $paginator->currentPage() > 1) {
            $this->page--;
            $this->onPageChange();
        } elseif (! $paginator && $this->page > 1) {
            $this->page--;
            $this->onPageChange();
        }
    }

    /**
     * Go to specific page.
     */
    #[Invokable]
    public function goToPage(int $page): void
    {
        $page = max(1, min($page, $this->getLastPage()));
        if ($page !== $this->page) {
            $this->page = $page;
            $this->onPageChange();
        }
    }

    /**
     * Reset to first page.
     */
    #[Invokable]
    public function resetPage(): void
    {
        if ($this->page !== 1) {
            $this->page = 1;
            $this->onPageChange();
        }
    }

    /**
     * Set items per page.
     */
    #[Invokable]
    public function setPerPage(int $perPage): void
    {
        $this->perPage = max(1, $perPage);
        $this->page = 1; // Reset to first page when changing per page
        $this->onPageChange();
    }

    /**
     * Get total number of pages.
     */
    public function getLastPage(): int
    {
        $paginator = $this->getPaginator();
        if ($paginator) {
            return $paginator->lastPage();
        }

        return 1;
    }

    /**
     * Check if there are more pages.
     */
    public function hasMorePages(): bool
    {
        $paginator = $this->getPaginator();
        if ($paginator) {
            return $paginator->hasMorePages();
        }

        return false;
    }

    /**
     * Check if there are previous pages.
     */
    public function hasPreviousPages(): bool
    {
        return $this->page > 1;
    }

    /**
     * Get the starting index for the current page (0-indexed).
     */
    public function getOffset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    /**
     * Get pagination info array.
     *
     * @return array<string, mixed>
     */
    public function getPaginationInfo(): array
    {
        $paginator = $this->getPaginator();
        if ($paginator) {
            return [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'has_more_pages' => $paginator->hasMorePages(),
                'has_previous_pages' => $paginator->currentPage() > 1,
            ];
        }

        return [
            'current_page' => $this->page,
            'per_page' => $this->perPage,
            'total' => 0,
            'last_page' => 1,
            'from' => 0,
            'to' => 0,
            'has_more_pages' => false,
            'has_previous_pages' => false,
        ];
    }

    /**
     * Hook called when page changes.
     * Override this method in your component to reload data.
     */
    protected function onPageChange(): void
    {
        // Override in component to reload data when page changes
    }

    /**
     * Get the paginator instance from component properties.
     * Automatically finds the first LengthAwarePaginator property.
     */
    public function getPaginator(): ?LengthAwarePaginator
    {
        foreach (get_object_vars($this) as $property => $value) {
            if ($value instanceof LengthAwarePaginator) {
                return $value;
            }
        }

        return null;
    }
}

