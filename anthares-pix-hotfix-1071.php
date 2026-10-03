<?php
/**
 * Plugin Name: Anthares PIX Hotfix 1.0.71
 * Description: Aplicador temporário da sincronização imediata de repasses.
 * Version: 1.0.0
 * Author: Anthares
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

register_activation_hook( __FILE__, function () {
    $root = WP_PLUGIN_DIR . '/anthares-pix-sales';
    $main = $root . '/anthares-pix-sales.php';
    $sale = $root . '/includes/61-loja-conto-avulso.php';

    if ( ! is_readable( $main ) || ! is_writable( $main ) || ! is_readable( $sale ) || ! is_writable( $sale ) ) {
        wp_die( 'Anthares PIX Hotfix: arquivos de destino sem permissão de leitura/escrita.' );
    }

    $main_src = file_get_contents( $main );
    $sale_src = file_get_contents( $sale );
    if ( false === $main_src || false === $sale_src ) {
        wp_die( 'Anthares PIX Hotfix: falha ao ler os arquivos de destino.' );
    }

    $sale_old = "\tdo_action( 'anthares_loja_venda_aprovada', \$venda );\n\treturn \$venda;";
    $sale_new = "\tdo_action( 'anthares_loja_venda_aprovada', \$venda );\n\n\t// Registra o repasse imediatamente; o cron permanece como reconciliação.\n\tif ( function_exists( 'anthares_repasses_sincronizar_vendas' ) ) {\n\t\tanthares_repasses_sincronizar_vendas();\n\t}\n\n\treturn \$venda;";

    $already = false !== strpos( $sale_src, "Registra o repasse imediatamente; o cron permanece como reconciliação." );
    if ( ! $already && 1 !== substr_count( $sale_src, $sale_old ) ) {
        wp_die( 'Anthares PIX Hotfix: ponto de alteração da venda não corresponde à versão esperada.' );
    }
    $sale_new_src = $already ? $sale_src : str_replace( $sale_old, $sale_new, $sale_src, $count );
    if ( ! $already && 1 !== $count ) {
        wp_die( 'Anthares PIX Hotfix: alteração da venda não pôde ser preparada.' );
    }

    $main_already = false !== strpos( $main_src, 'Version: 1.0.71' ) && false !== strpos( $main_src, "define( 'ANTHARES_PIX_VERSION', '1.0.71' );" );
    if ( $main_already ) {
        $main_new_src = $main_src;
    } else {
        $main_new_src = str_replace( 'Version: 1.0.70', 'Version: 1.0.71', $main_src, $c1 );
        $main_new_src = str_replace( "define( 'ANTHARES_PIX_VERSION', '1.0.70' );", "define( 'ANTHARES_PIX_VERSION', '1.0.71' );", $main_new_src, $c2 );
        if ( 1 !== $c1 || 1 !== $c2 ) {
            wp_die( 'Anthares PIX Hotfix: versão do plugin não corresponde à 1.0.70 esperada.' );
        }
    }

    if ( $sale_new_src !== $sale_src && false === file_put_contents( $sale, $sale_new_src, LOCK_EX ) ) {
        wp_die( 'Anthares PIX Hotfix: falha ao gravar a sincronização imediata.' );
    }
    if ( $main_new_src !== $main_src && false === file_put_contents( $main, $main_new_src, LOCK_EX ) ) {
        if ( $sale_new_src !== $sale_src ) { file_put_contents( $sale, $sale_src, LOCK_EX ); }
        wp_die( 'Anthares PIX Hotfix: falha ao atualizar a versão; alteração anterior restaurada.' );
    }

    if ( function_exists( 'opcache_invalidate' ) ) {
        @opcache_invalidate( $sale, true );
        @opcache_invalidate( $main, true );
    }
} );
