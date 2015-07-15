<?php

class WPSC_Settings_Tab_Retargeting extends WPSC_Settings_Tab
{
    const TAB_KEY = 'retargeting';
    const TAB_NAME = 'Retargeting';

    public function display()
    {
        settings_fields('retargeting');
        do_settings_sections('retargeting');
    }

    public function display_setting($args = array())
    {
        $html = '';

        switch ($args['type']) {
            case 'text':
                $html .= '<input type="text"';
                foreach ($args['html_options'] as $key => $value) {
                    $html .= ' ' . $key . '="' . esc_attr($value) . '"';
                }
                $html .= ' />';

                $html .= '<span class="howto">' . $args['desc'] . '</span>';
                $html .= '<ul>' . $args['pageids'] . '</ul>';
                break;

            case 'radio':
                foreach ($args['options'] as $option) {
                    $html .= '<input type="radio"';
                    foreach ($option['html_options'] as $key => $value) {
                        $html .= ' ' . $key . '="' . esc_attr($value) . '"';
                    }
                    $html .= checked($option['html_options']['value'], $args['default_value'], false);
                    $html .= ' />';
                    $html .= '&nbsp;';
                    $html .= '<label for="' . esc_attr($option['html_options']['id']) . '">';
                    $html .= esc_html($option['label']) . '</label>';
                    $html .= '&nbsp;';
                }
                $html .= '<span class="howto">' . esc_html($args['desc']) . '</span>';
                break;
            default:
                break;
        }
        echo $html;
    }
}
