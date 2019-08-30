+function($) {
    $(document).ready(function () {
        //вызовется после загрузки страницы
        function onLoad() {
            let address = localStorage.getItem('address');
            if (address) {
                checkVisibility();
                checkKeys();
            }
            loadTweets('', 'feeds');
            getBalance();
        }
        onLoad();

        //загрузка твитов
        function loadTweets(parent_tweet_id, container_class) {
            let id = 0;
            var request = { id: id, parent_tweet_id: parent_tweet_id, address: jsSettings.address };
            if (localStorage.getItem('address')) {
                request['current_address'] = localStorage.getItem('address');
            }
            $.ajax({
                type: 'GET',
                cache: false,
                url: '/tweets.php',
                data: request,
                success: function (data) {
                    data = JSON.parse(data);
                    $(data).each(function( index ) {
                        //смотрим, нет ли уже такого твита
                        let tweet = data[index]
                        if (!$('.' + container_class + ' > div[data-tweet_id="' + this.tweet_id + '"]').length) {
                            let div = '<div class="tweet" data-tweet_id="' + this.tweet_id + '">'
                                + '<div class="userpic">' +
                                '<div class="ava">' + this.icon + '</div>' +
                                '<div class="name" data-name="' + this.name + '"><a href="/?address=' + this.address + '">' + this.name + '</a></div>';
                            if (this.public_key != '' && localStorage.getItem('address')) {
                                div += '<div class="direct-link" name="' + this.name + '" address="' + this.address + '" public_key="' + this.public_key + '">Директ</div>';
                            }

                            div += '</div>'
                                + '<div class="tweet-body">' + this.message + '</div>';
                            div += '<div class="tweet-bottom">';
                            div += '<div class="links">';
                            if (localStorage.getItem('address')) {
                                div += '<span class="reply-link" data-tweet_id="' + this.tweet_id + '" data-name="' + this.name + '">Ответить</span>';
                            }
                            div += '<span class="like-link" data-tweet_id="' + this.tweet_id + '" data-liked="' + this.liked + '" data-likes="' + this.likes + '">' + this.likes + '</span>';
                            div += '</div>';
                            div += '<div class="datetime">' + this.created_at + '</div>';
                            div += '</div>';
                            div += '<div class="clear"></div>';
                            if (this.has_child == 1) {
                                div += '<div class="load-replies" data-tweet_id="' + this.tweet_id + '">Загрузить ответы</div><div class="replies_' + this.tweet_id + '"></div>'
                            }
                            div += '</div>';
                            $('div.' + container_class).prepend(div);
                        }
                    });
                },
                error: function (XMLHttpRequest, textStatus, errorThrown) {
                    console.log('Error');
                }
            });

            setTimeout(function(){
                loadTweets('', 'feeds');
            }, 5000);
        }

        //видимость блоков для анонимов и авторизованных
        function checkVisibility() {
            let address = localStorage.getItem('address');
            if (address) {
                $('.for-authorized').show();
                $('.for-anonymous').hide();
            } else {
                $('.for-authorized').hide();
                $('.for-anonymous').show();
            }
        }

        //твит
        $("#tweet-button").on("click", function () {
            $('#tweet_status').html('');
            let message = $('textarea#tweet-text').val();
            if (message.length == 0) {
                $('#tweet_status').html('Сообщение должно состоять хотя бы из одного символа.');
            }
            else if (message.length <= 800) {
                //пробуем сформировать транзакцию отправки монеты
                const minter = new minterSDK.Minter({apiType: 'node', baseURL: jsSettings.nodeUrl});
                let nonce = minter.getNonce(localStorage.getItem('address'));
                let tweet_id = generateRandomString();
                let payload = {message, tweet_id};
                if ($('textarea#tweet-text').attr('reply_to')) {
                    payload['reply_to'] = $('textarea#tweet-text').attr('reply_to');
                }
                const txParams = new minterSDK.SendTxParams({
                    privateKey: localStorage.getItem('privateKey'),
                    nonce: nonce,
                    chainId: 1,//prod
                    address: localStorage.getItem('address'),
                    amount: 0,
                    coinSymbol: jsSettings.token,
                    feeCoinSymbol: jsSettings.token,
                    gasPrice: 1,
                    message: JSON.stringify(payload),
                });

                minter.postTx(txParams)
                    .then((txHash) => {
                        $('#tweet_status').html('Сообщение успешно отправлено.<br /><a target="_blank" href="https://explorer.minter.network/transactions/' + txHash + '">Транзакция</a>');
                        $('#reply-info').html('');
                        $('#tweet-text').val('');

                        //отправка копии в БД
                        setTimeout(getBlocks, 1000);
                        setTimeout(getBlocks, 3000);
                        setTimeout(getBlocks, 8000);

                    }).catch((error) => {
                        $('#tweet_status').html('Ошибка создания транзакции.<br />' + error.response.data.error.tx_result.log);
                    });
            } else {
                $('#tweet_status').html('Сообщение должно быть короче 800 символов');
            }
        });

        //запись в директ
        $("#direct-button").on("click", function () {
            $('#tweet_status').html('');
            let message = $('textarea#direct-text').val();
            let address_to = $('textarea#direct-text').attr('address');
            let public_key = $('textarea#direct-text').attr('public_key');

            if (message.length == 0) {
                $('#direct_status').html('Сообщение должно состоять хотя бы из одного символа.');
            }
            else if (message.length <= 800) {
                var encrypted = cryptico.encrypt(message, public_key);
                if (encrypted.status == 'success') {
                    var encrypted_message = encrypted.cipher;
                    //пробуем сформировать транзакцию отправки монеты
                    const minter = new minterSDK.Minter({apiType: 'node', baseURL: jsSettings.nodeUrl});
                    let nonce = minter.getNonce(localStorage.getItem('address'));
                    let direct_id = generateRandomString();
                    let payload = {message: encrypted_message, direct_id: direct_id};

                    const txParams = new minterSDK.SendTxParams({
                        privateKey: localStorage.getItem('privateKey'),
                        nonce: nonce,
                        chainId: 1,//prod
                        address: address_to,
                        amount: 0,
                        coinSymbol: jsSettings.token,
                        feeCoinSymbol: jsSettings.token,
                        gasPrice: 1,
                        message: JSON.stringify(payload),
                    });

                    minter.postTx(txParams)
                        .then((txHash) => {
                            $('#direct_status').html('Сообщение успешно отправлено.<br /><a target="_blank" href="https://explorer.minter.network/transactions/' + txHash + '">Транзакция</a>');
                            $('#direct-text').val('');
                        }).catch((error) => {
                            $('#direct_status').html('Ошибка создания транзакции.<br />' + error.response.data.error.tx_result.log);
                        });
                } else {
                    $('#direct_status').html('Ошибка шифровки сообщения.');
                }
            } else {
                $('#direct_status').html('Сообщение должно быть короче 800 символов');
            }
        });

        //набор сообщения
        $("#tweet-text, #direct-text").on("keyup", function () {
            let message = $(this).val();
            let tweet_id = generateRandomString();
            let payload = { message };
            payload[$(this).attr('data-type') + '_id'] = tweet_id;
            let json = JSON.stringify(payload);
            let len = json.length;
            $('#' + $(this).attr('data-type') + '-tx-length').html(len + '/1024 размер сообщения транзакции.');
            if (len > 1024) {
                $('#' + $(this).attr('data-type') + '-tx-length').addClass('red');
            } else {
                $('#' + $(this).attr('data-type') + '-tx-length').removeClass('red');
            }
            var fee = minterUtil.getFeeValue('0x01', {payload: json});
            $('#' + $(this).attr('data-type') + '-tx-fee').html('Размер комиcсии ' + fee + ' BIP.');
        });

        //обновить данные в бд
        function getBlocks() {
            $.ajax({
                type: 'GET',
                cache: false,
                url: '/check_blocks.php',
                success: function (data) {
                    console.log(data);
                },
                error: function (XMLHttpRequest, textStatus, errorThrown) {
                    console.log('Error');
                }
            });
        }

        //получение баланса пользователя
        function getBalance() {
            if (localStorage.getItem('address')) {
                $.ajax({
                    type: 'GET',
                    cache: false,
                    url: '/get_user.php',
                    data: {
                        address: localStorage.getItem('address')
                    },
                    success: function (data) {
                        data = JSON.parse(data);
                        $('.balance').html(data['name'] + '<br />' + data['address'] + '<br />' + 'Баланс: ' + data['balance'] + ' ' + jsSettings.token);
                        if (data['icon']) {
                            $('div.home a').html(data['icon'])
                        }
                    },
                    error: function (XMLHttpRequest, textStatus, errorThrown) {
                        console.log('Error');
                    }
                });

                setTimeout(function(){
                    getBalance();
                }, 30000);
            }
        }

        //выход
        $("#logout").on("click", function () {
            if (localStorage.getItem('direct_secret')) {
                var exit = prompt('Вы уверены, что хотите выйти? Сохраните приватный ключ для сообщений.', localStorage.getItem('direct_secret'));
                if (exit !== null) {
                    localStorage.removeItem('address');
                    localStorage.removeItem('publicKey');
                    localStorage.removeItem('privateKey');
                    checkVisibility();
                }
            } else {
                localStorage.removeItem('address');
                localStorage.removeItem('publicKey');
                localStorage.removeItem('privateKey');
                checkVisibility();
            }
        });

        //отмена написания твита или ответа на твит
        $("#tweet-cancel").on("click", function () {
            $('textarea#tweet-text').val('');
            $('textarea#tweet-text').removeAttr('reply_to');
            $('div#reply-info').html('');
            $('div#tweet-tx-length').html('');
            $('div#tweet-tx-fee').html('');
        });

        //вывод формы для отправки директа
        $(".feeds").on("click", "div.direct-link", function () {
            showDirectForm(this);
        });
        $(".direct_list").on("click", "div.direct-link", function () {
            showDirectForm(this);
        });
        function showDirectForm(link) {
            $('.write-tweet').hide();
            $('.feeds').hide();
            $('textarea#direct-text').attr('address', $(link).attr('address'));
            $('textarea#direct-text').attr('public_key', $(link).attr('public_key'));
            $('.direct-form').show();
            $('#direct-info').html('Сообщение для ' + $(link).attr('name'));
        }

        //отмена написания директа
        $("#direct-cancel").on("click", function () {
            $('textarea#direct-text').val('');
            $('textarea#direct-text').removeAttr('address');
            $('div#direct-tx-length').html('');
            $('div#direct-tx-fee').html('');
            $('.direct-form').hide();
            $('.write-tweet').show();
            $('.feeds').show();
        });

        //вывод формы для отправки ответа на твит
        $(".feeds").on("click", "span.reply-link", function () {
            $('#reply-info').html('Ответ для ' + $(this).attr('data-name'));
            $('#tweet-text').attr('reply_to', $(this).attr('data-tweet_id'))
        });

        //вывод ответов на твит
        $(".feeds").on("click", "div.load-replies", function () {
            loadTweets($(this).attr('data-tweet_id'), 'replies_' + $(this).attr('data-tweet_id'));
        });

        //клик по сердечку
        $(".feeds").on("click", "span.like-link", function () {
            if (localStorage.getItem('address')) {
                const minter = new minterSDK.Minter({apiType: 'node', baseURL: jsSettings.nodeUrl});
                let nonce = minter.getNonce(localStorage.getItem('address'));

                let payload = {};
                var likes = parseInt($(this).attr('data-likes'));
                if ($(this).attr('data-liked') == 'false') {
                    //ставим лайк
                    payload = {like: $(this).attr('data-tweet_id')};
                    likes++;
                } else {
                    //снимаем лайк
                    payload = {unlike: $(this).attr('data-tweet_id')};
                    likes--;
                }

                const txParams = new minterSDK.SendTxParams({
                    privateKey: localStorage.getItem('privateKey'),
                    nonce: nonce,
                    chainId: 1,//prod
                    address: localStorage.getItem('address'),
                    amount: 0,
                    coinSymbol: jsSettings.token,
                    feeCoinSymbol: jsSettings.token,
                    gasPrice: 1,
                    message: JSON.stringify(payload),
                });

                minter.postTx(txParams)
                    .then((txHash) => {
                        if ($(this).attr('data-liked') == 'false') {
                            $(this).attr('data-liked', 'true');
                        } else {
                            $(this).attr('data-liked', 'false');
                        }
                        $(this).attr('data-likes', likes);
                        $(this).html(likes);
                        console.log('https://explorer.minter.network/transactions/' + txHash);

                        //получение копии в БД
                        setTimeout(getBlocks, 1000);
                        setTimeout(getBlocks, 3000);
                        setTimeout(getBlocks, 8000);
                    }).catch((error) => {
                        // $(this).html('Ошибка создания транзакции.<br />' + error.response.data.error.tx_result.log);
                    });
            }
        });

        //авторизация
        $("#auth-button").on("click", function () {
            let mnemonic = $('textarea#mnemonic').val();

            let valid_mnemonic = minterWallet.isValidMnemonic(mnemonic);
            if (valid_mnemonic) {
                let wallet = minterWallet.walletFromMnemonic(mnemonic);
                let privateKey = wallet.getPrivateKeyString();
                let publicKey = wallet.getPublicKeyString();
                let address = wallet.getAddressString();

                localStorage.setItem('address', address);
                localStorage.setItem('publicKey', publicKey);
                localStorage.setItem('privateKey', privateKey);

                onLoad();
            } else {
                $('#auth_status').html('Некорректная seed-фраза');
            }
        });

        //показ списка сообщений в директ
        $(".show-directs").on("click", function () {
            if (!$(this).attr('shown')) {
                $(this).attr('shown', 'true');
                $(this).html('Закрыть директ');

                $('.loader').show();
                $('.direct_list').html('<span class="loader">Загрузка...</span>');
                $.ajax({
                    type: 'POST',
                    cache: false,
                    url: '/get_direct.php',
                    data: {
                        address: localStorage.getItem('address')
                    },
                    success: function (data) {
                        data = JSON.parse(data);
                        $('.loader').hide();
                        if (data.length == 0) {
                            $('.direct_list').html('У вас пока нет личных сообщений.');
                        }
                        var RSAkey = cryptico.generateRSAKey(localStorage.getItem('direct_secret'), 1024);
                        $(data).each(function( index ) {
                            var DecryptionResult = cryptico.decrypt(this.message, RSAkey);
                            if (DecryptionResult.status == 'success') {
                                //смотрим, нет ли уже такого твита
                                if (!$('.direct_list > div[data-direct_id="' + this.direct_id + '"]').length) {
                                    let div = '<div class="direct" data-direct_id="' + this.direct_id + '">'
                                        + '<div class="userpic">' +
                                        '<div class="ava">' + this.icon + '</div>' +
                                        '<div class="name" data-name="' + this.name + '">' + this.name + '</div>';
                                    div += '</div>'
                                        + '<div class="tweet-body">' + DecryptionResult.plaintext + '</div>';
                                    div += '<div class="tweet-bottom">';
                                    if (this.public_key != '') {
                                        div += '<div class="links">' +
                                            '<div class="direct-link" name="' + this.name + '" address="' + this.address + '" data-direct_id="' + this.direct_id + '" public_key="' + this.public_key + '">Ответить в директ</div>' +
                                            '</div>';
                                    }
                                    div += '<div class="datetime">' + this.created_at + '</div>';
                                    div += '</div>';
                                    div += '<div class="clear"></div>';
                                    div += '</div>';
                                    $('div.direct_list').prepend(div);
                                }
                            }
                        });
                    },
                    error: function (XMLHttpRequest, textStatus, errorThrown) {
                        console.log('Error');
                    }
                });
            } else {
                $(this).html('Открыть директ');
                $(this).removeAttr('shown');
                $('.direct_list').html('');
            }
        });

        //генерация приватного ключа
        $("#generate-private-key").on("click", function () {
            $('#generated-private').val(generateRandomString());
        });

        //импорт приватного ключа
        $("#use-private-key").on("click", function () {
            var direct_secret = $('#import-private').val();
            if (direct_secret != '') {
                localStorage.setItem('direct_secret', direct_secret);
                $('#import-private').hide();
                $('#use-private-key').hide();
                $('#cancel_load_private_key').hide();
                checkKeys();
            }
        });

        //генерация публичного ключа и отправка его в блокчейн
        $("#save_public_key").on("click", function () {
            // формирование приватной части ключа для директа
            var direct_secret = $('#generated-private').val();
            if (direct_secret != '') {
                var RSAkey = cryptico.generateRSAKey(direct_secret, 1024);
                var public_key = cryptico.publicKeyString(RSAkey);

                const minter = new minterSDK.Minter({apiType: 'node', baseURL: jsSettings.nodeUrl});
                let nonce = minter.getNonce(localStorage.getItem('address'));
                let payload = { public: public_key };
                const txParams = new minterSDK.SendTxParams({
                    privateKey: localStorage.getItem('privateKey'),
                    nonce: nonce,
                    chainId: 1,//prod
                    address: localStorage.getItem('address'),
                    amount: 0,
                    coinSymbol: jsSettings.token,
                    feeCoinSymbol: jsSettings.token,
                    gasPrice: 1,
                    message: JSON.stringify(payload),
                });

                minter.postTx(txParams)
                    .then((txHash) => {
                        $('#key_status').html('Публичный ключ успешно отправлен, ожидайте проверки от блокчейна.<br /><a target="_blank" href="https://explorer.minter.network/transactions/' + txHash + '">Транзакция</a>');

                        localStorage.setItem('direct_secret', direct_secret);

                        //поиск ключа в БД
                        setTimeout(getBlocks, 1000);
                        setTimeout(checkKeys, 2000);

                        setTimeout(getBlocks, 3000);
                        setTimeout(checkKeys, 4000);

                        setTimeout(getBlocks, 8000);
                        setTimeout(checkKeys, 9000);

                        setTimeout(getBlocks, 10000);
                        setTimeout(checkKeys, 11000);
                    }).catch((error) => {
                        $('#key_status').html('Ошибка создания транзакции.<br />' + error.response.data.error.tx_result.log);
                    });
            }
        });

        //показ формы для импорта приватного ключа
        $("#load_private_key").on("click", function () {
            $('#import-private').show();
            $('#use-private-key').show();

            $('#generated-private').hide();
            $('#generate-private-key').hide();
            $('#save_public_key').hide();
            $('#load_private_key').hide();
            $('#cancel_load_private_key').show();
        });

        //отмена показа формы импорта приватного ключа
        $("#cancel_load_private_key").on("click", function () {
            $('#import-private').hide();
            $('#use-private-key').hide();

            $('#generated-private').show();
            $('#generate-private-key').show();
            $('#save_public_key').show();
            $('#load_private_key').show();
            $('#cancel_load_private_key').hide();
        });

        //проверка - есть ли приватный ключ
        function checkKeys() {
            let direct_secret = localStorage.getItem('direct_secret');

            if (!direct_secret) {
                $('.keys-status').show();
                $('#direct_data').hide();
                $('#generated-private').val(generateRandomString())
            } else {
                $('.keys-status').hide();
                $('#direct_data').show();

            }
        }

        //генерация раномной строки
        function generateRandomString() {
            return [...Array(30)].map(i=>(~~(Math.random()*36)).toString(36)).join('').toUpperCase();
        }
    });
}(jQuery);