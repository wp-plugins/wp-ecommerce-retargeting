<?php if (isset($product) && is_array($product)): ?>

    <script type="text/javascript">
        var _ra = _ra || {};
        _ra.sendProductInfo = {
            "id": <?php echo esc_html( $product['product_id'] ); ?>,
            "name": "<?php echo esc_html( $product['name'] ); ?>",
            "url": "<?php echo esc_html( $product['url'] ); ?>",
            "img": "<?php echo esc_html( $product['image_url'] ); ?>",
            "price": <?php echo esc_html( $product['list_price'] ); ?>,
            "promo": <?php echo ($product['price'] - $product['list_price'] != 0 ? esc_html( $product['price'] ) : 0 ) ?>,
            "stock": <?php echo esc_html( $product['stock'] ); ?>,
            "brand": false,
            "category": {
                "id": <?php echo esc_html($product['category_id']); ?>,
                "name": "<?php echo esc_html($product['category_name']); ?>",
                "parent": false
            },
            "category_breadcrumb": []
        }
        if (_ra.ready !== undefined) {
            _ra.sendProduct(_ra.sendProductInfo);
        }

        jQuery(document).ready(function () {
            jQuery(".wpsc_buy_button").click(function () {
                _ra.addToCart(<?php echo esc_html( $product['product_id'] ); ?>, false, function () {
                    console.log("cart")
                });
            });
        });


        if (typeof FB != 'undefined') {
            FB.Event.subscribe('edge.create', function () {
                _ra.likeFacebook(<?php echo esc_html( $product['product_id'] ); ?>);
            });
        }
        ;

        function _ra_helper_addLoadEvent(func) {
            var oldonload = window.onload;
            if (typeof window.onload != "function") {
                window.onload = func;
            }
            else {
                window.onload = function () {
                    if (oldonload) {
                        oldonload();
                    }
                    func();
                }
            }
        }
        function _ra_triggerClickImage() {
            if (typeof _ra.clickImage !== "undefined") _ra.clickImage(<?php echo esc_html( $product['product_id'] ); ?>);
        }
        _ra_helper_addLoadEvent(function () {
            if (document.getElementsByClassName("product_image").length > 0) {
                document.getElementsByClassName("product_image")[0].onmouseover = _ra_triggerClickImage;
            }

            if (document.getElementsByClassName("product_image").length > 0) {
                document.getElementsByClassName("product_image")[0].onmouseover = _ra_triggerClickImage;
            }
        });

        jQuery(document).ready(function () {

            var _ra_sv = document.querySelectorAll(".wpsc_select_variation");

            if (_ra_sv.length > 0) {
                for (var i = 0; i < _ra_sv.length; i++) {
                    _ra_sv[i].addEventListener("change", function () {
                        var _ra_vcode = [], _ra_vdetails = {};
                        var _ra_v = document.querySelectorAll(".wpsc_select_variation");
                        for (var i = 0; i < _ra_v.length; i++) {
                            _ra_label = document.querySelector('[for="' + _ra_v[i].getAttribute('id') + '"').innerText;
                            _ra_value = document.getElementById(_ra_v[i].getAttribute("id")).options[document.getElementById(_ra_v[i].getAttribute("id")).selectedIndex].text;
                            _ra_vcode.push(_ra_value);
                            _ra_vdetails[_ra_value] = {
                                "category_name": _ra_label,
                                "category": _ra_label,
                                "value": _ra_value
                            };
                        }
                        _ra.setVariation(<?php echo esc_html( $product['product_id'] ); ?>, {
                            "code": _ra_vcode.join('-'),
                            "details": _ra_vdetails
                        });
                    });
                }
            }


        });

    </script>

<?php endif; ?>