<?php
// File: src/utils/pagination_helper.php

/**
 * Classe helper per gestire la paginazione in modo consistente
 */
class PaginationHelper {

    private int $totalItems;
    private int $itemsPerPage;
    private int $currentPage;
    private int $totalPages;

    public function __construct(int $totalItems, int $itemsPerPage = 10, int $currentPage = 1) {
        $this->totalItems = $totalItems;
        $this->itemsPerPage = $itemsPerPage;
        $this->currentPage = max(1, $currentPage);
        $this->totalPages = (int)ceil($totalItems / $itemsPerPage);

        // Assicura che la pagina corrente non superi il totale
        if ($this->currentPage > $this->totalPages && $this->totalPages > 0) {
            $this->currentPage = $this->totalPages;
        }
    }

    /**
     * Restituisce l'offset per la query SQL
     */
    public function getOffset(): int {
        return ($this->currentPage - 1) * $this->itemsPerPage;
    }

    /**
     * Restituisce il limite per la query SQL
     */
    public function getLimit(): int {
        return $this->itemsPerPage;
    }

    /**
     * Restituisce il numero totale di pagine
     */
    public function getTotalPages(): int {
        return $this->totalPages;
    }

    /**
     * Restituisce la pagina corrente
     */
    public function getCurrentPage(): int {
        return $this->currentPage;
    }

    /**
     * Verifica se esiste una pagina precedente
     */
    public function hasPrevious(): bool {
        return $this->currentPage > 1;
    }

    /**
     * Verifica se esiste una pagina successiva
     */
    public function hasNext(): bool {
        return $this->currentPage < $this->totalPages;
    }

    /**
     * Restituisce il numero della pagina precedente
     */
    public function getPreviousPage(): int {
        return max(1, $this->currentPage - 1);
    }

    /**
     * Restituisce il numero della pagina successiva
     */
    public function getNextPage(): int {
        return min($this->totalPages, $this->currentPage + 1);
    }

    /**
     * Genera l'HTML della paginazione
     */
    public function render(string $baseUrl, array $queryParams = []): string {
        if ($this->totalPages <= 1) {
            return '';
        }

        // Rimuovi 'page' dai parametri se esiste
        unset($queryParams['page']);

        $html = '<div class="pagination-container">';
        $html .= '<div class="pagination-info">';
        $html .= 'Pagina <strong>' . $this->currentPage . '</strong> di <strong>' . $this->totalPages . '</strong>';
        $html .= ' (<strong>' . $this->totalItems . '</strong> risultati totali)';
        $html .= '</div>';

        $html .= '<div class="pagination">';

        // Pulsante Precedente
        if ($this->hasPrevious()) {
            $url = $this->buildUrl($baseUrl, $queryParams, $this->getPreviousPage());
            $html .= '<a href="' . htmlspecialchars($url) . '" class="pagination-btn">';
            $html .= '<span class="arrow left">←</span> Precedente';
            $html .= '</a>';
        } else {
            $html .= '<span class="pagination-btn disabled">';
            $html .= '<span class="arrow left">←</span> Precedente';
            $html .= '</span>';
        }

        // Numeri di pagina
        $html .= $this->renderPageNumbers($baseUrl, $queryParams);

        // Pulsante Successivo
        if ($this->hasNext()) {
            $url = $this->buildUrl($baseUrl, $queryParams, $this->getNextPage());
            $html .= '<a href="' . htmlspecialchars($url) . '" class="pagination-btn">';
            $html .= 'Successivo <span class="arrow right">→</span>';
            $html .= '</a>';
        } else {
            $html .= '<span class="pagination-btn disabled">';
            $html .= 'Successivo <span class="arrow right">→</span>';
            $html .= '</span>';
        }

        $html .= '</div>'; // chiude .pagination
        $html .= '</div>'; // chiude .pagination-container

        return $html;
    }

    /**
     * Genera i numeri di pagina con logica intelligente
     */
    private function renderPageNumbers(string $baseUrl, array $queryParams): string {
        $html = '';
        $delta = 2;
        $start = max(1, $this->currentPage - $delta);
        $end = min($this->totalPages, $this->currentPage + $delta);

        // Mostra prima pagina e "..." se necessario
        if ($start > 1) {
            $url = $this->buildUrl($baseUrl, $queryParams, 1);
            $html .= '<a href="' . htmlspecialchars($url) . '" class="pagination-number">1</a>';
            if ($start > 2) {
                $html .= '<span class="pagination-dots">...</span>';
            }
        }

        // Pagine centrali
        for ($i = $start; $i <= $end; $i++) {
            if ($i === $this->currentPage) {
                $html .= '<span class="pagination-number active">' . $i . '</span>';
            } else {
                $url = $this->buildUrl($baseUrl, $queryParams, $i);
                $html .= '<a href="' . htmlspecialchars($url) . '" class="pagination-number">' . $i . '</a>';
            }
        }

        // Mostra ultima pagina e "..." se necessario
        if ($end < $this->totalPages) {
            if ($end < $this->totalPages - 1) {
                $html .= '<span class="pagination-dots">...</span>';
            }
            $url = $this->buildUrl($baseUrl, $queryParams, $this->totalPages);
            $html .= '<a href="' . htmlspecialchars($url) . '" class="pagination-number">' . $this->totalPages . '</a>';
        }

        return $html;
    }


    /**
     * Costruisce l'URL con i parametri di query
     */
    private function buildUrl(string $baseUrl, array $queryParams, int $page): string {
        $queryParams['page'] = $page;
        $queryString = http_build_query($queryParams);
        return $baseUrl . ($queryString ? '?' . $queryString : '');
    }

    /**
     * Restituisce informazioni sulla paginazione per JavaScript
     */
    public function getJsonInfo(): string {
        return json_encode([
            'currentPage' => $this->currentPage,
            'totalPages' => $this->totalPages,
            'totalItems' => $this->totalItems,
            'itemsPerPage' => $this->itemsPerPage,
            'hasNext' => $this->hasNext(),
            'hasPrevious' => $this->hasPrevious()
        ]);
    }
}
