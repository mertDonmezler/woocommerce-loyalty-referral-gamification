<?php
/**
 * Hook Loader
 *
 * WordPress action ve filter hook'larini merkezi olarak yonetir.
 * Tum hook'lar topluca kaydedilerek daha iyi organizasyon saglanir.
 *
 * @package WPGamify
 */

defined( 'ABSPATH' ) || exit;

class WPGamify_Loader {

    /**
     * Kayitli action hook'lari.
     *
     * @var array<int, array{hook: string, component: object|array, callback: string, priority: int, accepted_args: int}>
     */
    private array $actions = [];

    /**
     * Kayitli filter hook'lari.
     *
     * @var array<int, array{hook: string, component: object|array, callback: string, priority: int, accepted_args: int}>
     */
    private array $filters = [];

    /**
     * Yeni bir action hook'u kaydeder.
     *
     * @param string       $hook          Hook adi.
     * @param object|array $component     Sinif ornegi veya callable dizi.
     * @param string       $callback      Metod adi.
     * @param int          $priority      Oncelik (varsayilan: 10).
     * @param int          $accepted_args Kabul edilen parametre sayisi.
     * @return void
     */
    public function add_action(
        string $hook,
        object|array $component,
        string $callback,
        int $priority = 10,
        int $accepted_args = 1
    ): void {
        $this->actions[] = [
            'hook'          => $hook,
            'component'     => $component,
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args,
        ];
    }

    /**
     * Yeni bir filter hook'u kaydeder.
     *
     * @param string       $hook          Hook adi.
     * @param object|array $component     Sinif ornegi veya callable dizi.
     * @param string       $callback      Metod adi.
     * @param int          $priority      Oncelik (varsayilan: 10).
     * @param int          $accepted_args Kabul edilen parametre sayisi.
     * @return void
     */
    public function add_filter(
        string $hook,
        object|array $component,
        string $callback,
        int $priority = 10,
        int $accepted_args = 1
    ): void {
        $this->filters[] = [
            'hook'          => $hook,
            'component'     => $component,
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args,
        ];
    }

    /**
     * Kayitli tum hook'lari WordPress'e kaydeder.
     *
     * @return void
     */
    public function run(): void {
        foreach ( $this->filters as $filter ) {
            $callable = is_array( $filter['component'] )
                ? $filter['component']
                : [ $filter['component'], $filter['callback'] ];

            add_filter(
                $filter['hook'],
                $callable,
                $filter['priority'],
                $filter['accepted_args']
            );
        }

        foreach ( $this->actions as $action ) {
            $callable = is_array( $action['component'] )
                ? $action['component']
                : [ $action['component'], $action['callback'] ];

            add_action(
                $action['hook'],
                $callable,
                $action['priority'],
                $action['accepted_args']
            );
        }
    }

    /**
     * Kayitli action sayisini dondurur.
     *
     * @return int
     */
    public function get_action_count(): int {
        return count( $this->actions );
    }

    /**
     * Kayitli filter sayisini dondurur.
     *
     * @return int
     */
    public function get_filter_count(): int {
        return count( $this->filters );
    }
}
