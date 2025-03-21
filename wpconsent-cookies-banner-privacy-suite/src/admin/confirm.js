window.WPConsentConfirm = window.WPConsentConfirm || (
    function (document, window, $) {
        const app = {
            please_wait: null,
            show_please_wait(title = wpconsent.please_wait) {
                let spinner = '<div class="wpconsent-loading-ring"></div>';
                this.please_wait = $.confirm({
                    title: title,
                    closeIcon: false,
                    content: spinner,
                    boxWidth: '600px',
                    theme: 'modern loader-spinner',
                    buttons: {
                        close: {
                            isHidden: true
                        }
                    },
                    onOpenBefore: function () {
                        this.buttons.close.hide();
                        this.$content.parent().addClass('jconfirm-loading');
                    },
                    onClose: function () {
                        this.$content.parent().removeClass('jconfirm-loading');
                    }
                });
                return this.please_wait;
            },
            close() {
                if (this.please_wait) {
                    this.please_wait.close();
                }
            }
        };
        return app;
    }(document, window, jQuery)
); 