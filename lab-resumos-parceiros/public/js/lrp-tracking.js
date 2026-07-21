/**
 * Lab Resumos Parceiros - Script de Tracking Enriquecido
 * 
 * Captura UTMs, referrer, device type e envia para o servidor
 * @version 1.2.0
 */
(function() {
    'use strict';
    
    var LRP_Tracking = {
        
        /**
         * Inicializa o tracking
         */
        init: function() {
            var ref = this.getRefParam();
            
            if (ref) {
                this.registerVisit(ref);
                // Limpa URL após pequeno delay
                setTimeout(this.cleanUrl, 100);
            }
        },
        
        /**
         * Obtém parâmetro ref da URL
         */
        getRefParam: function() {
            var urlParams = new URLSearchParams(window.location.search);
            return urlParams.get('ref');
        },
        
        /**
         * Obtém todos os parâmetros UTM da URL
         */
        getUtmParams: function() {
            var urlParams = new URLSearchParams(window.location.search);
            return {
                utm_source: urlParams.get('utm_source') || '',
                utm_medium: urlParams.get('utm_medium') || '',
                utm_campaign: urlParams.get('utm_campaign') || '',
                utm_term: urlParams.get('utm_term') || '',
                utm_content: urlParams.get('utm_content') || ''
            };
        },
        
        /**
         * Detecta o tipo de dispositivo
         */
        getDeviceType: function() {
            var width = window.innerWidth;
            var userAgent = navigator.userAgent.toLowerCase();
            
            // Verifica por user agent primeiro
            if (/mobile|android|iphone|ipod|blackberry|windows phone/i.test(userAgent)) {
                return 'mobile';
            }
            if (/ipad|tablet|playbook|silk/i.test(userAgent)) {
                return 'tablet';
            }
            
            // Fallback por largura de tela
            if (width < 768) return 'mobile';
            if (width < 1024) return 'tablet';
            return 'desktop';
        },
        
        /**
         * Detecta o navegador
         */
        getBrowser: function() {
            var userAgent = navigator.userAgent;
            
            if (userAgent.indexOf('Firefox') > -1) return 'Firefox';
            if (userAgent.indexOf('SamsungBrowser') > -1) return 'Samsung';
            if (userAgent.indexOf('Opera') > -1 || userAgent.indexOf('OPR') > -1) return 'Opera';
            if (userAgent.indexOf('Trident') > -1) return 'IE';
            if (userAgent.indexOf('Edge') > -1) return 'Edge';
            if (userAgent.indexOf('Edg') > -1) return 'Edge';
            if (userAgent.indexOf('Chrome') > -1) return 'Chrome';
            if (userAgent.indexOf('Safari') > -1) return 'Safari';
            
            return 'Other';
        },
        
        /**
         * Parseia a fonte de tráfego a partir do referrer
         */
        parseTrafficSource: function(referrer) {
            if (!referrer) return 'direct';
            
            var sources = {
                'instagram.com': 'Instagram',
                'l.instagram.com': 'Instagram',
                'facebook.com': 'Facebook',
                'l.facebook.com': 'Facebook',
                'm.facebook.com': 'Facebook',
                'fb.com': 'Facebook',
                'youtube.com': 'YouTube',
                'youtu.be': 'YouTube',
                'm.youtube.com': 'YouTube',
                'tiktok.com': 'TikTok',
                'vm.tiktok.com': 'TikTok',
                'twitter.com': 'Twitter',
                'x.com': 'Twitter',
                't.co': 'Twitter',
                'whatsapp.com': 'WhatsApp',
                'wa.me': 'WhatsApp',
                'web.whatsapp.com': 'WhatsApp',
                'api.whatsapp.com': 'WhatsApp',
                't.me': 'Telegram',
                'telegram.me': 'Telegram',
                'telegram.org': 'Telegram',
                'google.com': 'Google',
                'google.com.br': 'Google',
                'linkedin.com': 'LinkedIn',
                'pinterest.com': 'Pinterest',
                'reddit.com': 'Reddit',
                'bing.com': 'Bing',
                'yahoo.com': 'Yahoo'
            };
            
            try {
                var url = new URL(referrer);
                var hostname = url.hostname.toLowerCase();
                
                // Remove www. se presente
                hostname = hostname.replace(/^www\./, '');
                
                // Verifica mapeamento direto
                for (var domain in sources) {
                    if (hostname === domain || hostname.endsWith('.' + domain)) {
                        return sources[domain];
                    }
                }
                
                // Retorna o hostname se não reconhecido
                return hostname;
            } catch (e) {
                return 'unknown';
            }
        },
        
        /**
         * Registra a visita via AJAX
         */
        registerVisit: function(refCode) {
            var self = this;
            var utmParams = this.getUtmParams();
            var referrer = document.referrer || '';
            
            var data = {
                action: 'lrp_register_visit',
                nonce: lrp_params.nonce,
                ref: refCode,
                landing_page: window.location.pathname + window.location.search,
                referrer_url: referrer,
                utm_source: utmParams.utm_source,
                utm_medium: utmParams.utm_medium,
                utm_campaign: utmParams.utm_campaign,
                utm_term: utmParams.utm_term,
                utm_content: utmParams.utm_content,
                traffic_source: utmParams.utm_source || this.parseTrafficSource(referrer),
                device_type: this.getDeviceType(),
                browser: this.getBrowser(),
                screen_width: window.innerWidth,
                screen_height: window.innerHeight
            };
            
            // Se UTM source está presente, usa como traffic_source
            if (utmParams.utm_source) {
                data.traffic_source = this.normalizeTrafficSource(utmParams.utm_source);
            }
            
            // Envia via fetch (mais moderno) ou fallback para XMLHttpRequest
            if (typeof fetch !== 'undefined') {
                fetch(lrp_params.ajax_url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: this.serializeData(data)
                }).catch(function(error) {
                    // Silenciosamente ignora erros de tracking
                    console.debug('LRP Tracking:', error);
                });
            } else {
                // Fallback para navegadores antigos
                var xhr = new XMLHttpRequest();
                xhr.open('POST', lrp_params.ajax_url, true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.send(this.serializeData(data));
            }
            
            // Seta cookie localmente também
            this.setCookie(lrp_params.cookie_name, refCode, lrp_params.cookie_days);
        },
        
        /**
         * Normaliza o nome da fonte de tráfego
         */
        normalizeTrafficSource: function(source) {
            if (!source) return 'direct';
            
            var normalized = source.toLowerCase();
            
            var mappings = {
                'ig': 'Instagram',
                'insta': 'Instagram',
                'instagram': 'Instagram',
                'fb': 'Facebook',
                'facebook': 'Facebook',
                'yt': 'YouTube',
                'youtube': 'YouTube',
                'tt': 'TikTok',
                'tiktok': 'TikTok',
                'tw': 'Twitter',
                'twitter': 'Twitter',
                'x': 'Twitter',
                'wpp': 'WhatsApp',
                'whatsapp': 'WhatsApp',
                'wa': 'WhatsApp',
                'tg': 'Telegram',
                'telegram': 'Telegram',
                'google': 'Google',
                'linkedin': 'LinkedIn',
                'ln': 'LinkedIn',
                'email': 'Email',
                'newsletter': 'Email'
            };
            
            return mappings[normalized] || source;
        },
        
        /**
         * Serializa dados para envio
         */
        serializeData: function(data) {
            var parts = [];
            for (var key in data) {
                if (data.hasOwnProperty(key)) {
                    parts.push(encodeURIComponent(key) + '=' + encodeURIComponent(data[key]));
                }
            }
            return parts.join('&');
        },
        
        /**
         * Define um cookie
         */
        setCookie: function(name, value, days) {
            var date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            var expires = 'expires=' + date.toUTCString();
            document.cookie = name + '=' + value + ';' + expires + ';path=/;SameSite=Lax';
        },
        
        /**
         * Remove parâmetro ref da URL (limpa)
         */
        cleanUrl: function() {
            var url = new URL(window.location.href);
            url.searchParams.delete('ref');
            
            if (window.history.replaceState) {
                window.history.replaceState({}, '', url.toString());
            }
        }
    };
    
    // Inicializa quando o DOM estiver pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            LRP_Tracking.init();
        });
    } else {
        LRP_Tracking.init();
    }
})();
