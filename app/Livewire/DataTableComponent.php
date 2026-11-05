<?php

namespace App\Livewire;

use App\View\Components\DataTable\Pagination;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

abstract class DataTableComponent extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    public string $searchInput = '';

    /** @var array<string, string|list<string>> */
    public array $filters = [];

    /** @var array<string, string|list<string>> */
    public array $appliedFilters = [];

    public int $perPage = Pagination::PER_PAGE_OPTIONS[0];

    /** @return array<string, array{label: string, options: array<string, string>, multiple?: bool}> */
    abstract protected function filterDefinitions(): array;

    public function mountDataTableComponent(): void
    {
        $this->searchInput = $this->search;
    }

    public function applySearch(): void
    {
        $this->search = trim($this->searchInput);
        $this->searchInput = $this->search;
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        if (! in_array($this->perPage, Pagination::PER_PAGE_OPTIONS, true)) {
            $this->perPage = Pagination::PER_PAGE_OPTIONS[0];
        }

        $this->resetPage();
    }

    /** @return list<array{key: string, label: string}> */
    #[Computed]
    public function activeFilters(): array
    {
        $active = [];

        if ($this->searchTerm() !== null) {
            $active[] = ['key' => 'search', 'label' => __('messages.search_term').': '.$this->searchTerm()];
        }

        foreach ($this->filterDefinitions() as $key => $definition) {
            if ($definition['multiple'] ?? false) {
                foreach ($this->appliedFilterValues($key) as $value) {
                    $active[] = ['key' => "{$key}:{$value}", 'label' => $definition['label'].': '.$definition['options'][$value]];
                }

                continue;
            }

            $value = $this->appliedFilter($key);

            if ($value !== null) {
                $active[] = ['key' => $key, 'label' => $definition['label'].': '.$definition['options'][$value]];
            }
        }

        return $active;
    }

    public function applyFilters(): void
    {
        $definitions = $this->filterDefinitions();
        $rules = [];
        $attributes = [];

        foreach ($definitions as $key => $definition) {
            $allowed = Rule::in(array_map(strval(...), array_keys($definition['options'])));
            $attributes["filters.{$key}"] = $definition['label'];

            if ($definition['multiple'] ?? false) {
                $rules["filters.{$key}"] = ['nullable', 'array'];
                $rules["filters.{$key}.*"] = ['string', $allowed];
                $attributes["filters.{$key}.*"] = $definition['label'];
            } else {
                $rules["filters.{$key}"] = ['nullable', 'string', $allowed];
            }
        }

        $this->validate($rules, attributes: $attributes);

        $this->appliedFilters = array_filter(
            array_intersect_key($this->filters, $definitions),
            fn (string|array|null $value) => $value !== null && $value !== '' && $value !== [],
        );
        $this->resetPage();
    }

    public function removeFilter(string $key): void
    {
        if ($key === 'search') {
            $this->reset('search', 'searchInput');
        }

        [$name, $value] = array_pad(explode(':', $key, 2), 2, null);

        if ($value !== null && ($this->filterDefinitions()[$name]['multiple'] ?? false)) {
            $this->filters[$name] = $this->withoutValue($this->filters[$name] ?? [], $value);
            $this->appliedFilters[$name] = $this->withoutValue($this->appliedFilters[$name] ?? [], $value);

            if ($this->appliedFilters[$name] === []) {
                unset($this->appliedFilters[$name]);
            }
        } else {
            unset($this->filters[$key], $this->appliedFilters[$key]);
        }

        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'searchInput', 'filters', 'appliedFilters');
        $this->resetValidation();
        $this->resetPage();
    }

    protected function searchTerm(): ?string
    {
        $search = trim($this->search);

        return $search === '' ? null : $search;
    }

    protected function appliedFilter(string $key): ?string
    {
        $value = $this->appliedFilters[$key] ?? null;

        return is_string($value) && array_key_exists($value, $this->filterDefinitions()[$key]['options'] ?? []) ? $value : null;
    }

    /** @return list<string> */
    protected function appliedFilterValues(string $key): array
    {
        $values = $this->appliedFilters[$key] ?? [];
        $options = $this->filterDefinitions()[$key]['options'] ?? [];

        return array_values(array_filter((array) $values, fn (string $value) => array_key_exists($value, $options)));
    }

    /**
     * @param  string|list<string>  $values
     * @return list<string>
     */
    private function withoutValue(string|array $values, string $value): array
    {
        return array_values(array_diff((array) $values, [$value]));
    }
}
