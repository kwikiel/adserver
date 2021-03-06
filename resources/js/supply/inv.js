/*
 * Copyright (c) 2018 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute and/or modify it
 * under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AdServer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AdServer. If not, see <https://www.gnu.org/licenses/>
 */

// Internet Explorer 8-11
var isIE = !!document.documentMode;
// Edge 20+
var isEdge = !isIE && !!window.StyleMedia;
// Chrome on iOs has bug with blob url in iframe.src
var isCriOS = !navigator.userAgent || !navigator.userAgent.match || navigator.userAgent.match('CriOS');

var tmp = new XMLHttpRequest();
supportBinaryFetch = !isIE || tmp.upload;

// IE does not accept blob url for iframe
var iframeDataUri = !isIE && !isEdge;

var requestBlob = window.Blob && window.FileReader && FileReader.prototype.hasOwnProperty('readAsBinaryString')
        && (FileReader.prototype.hasOwnProperty('readAsDataURL') || window.URL && URL.createObjectURL);

function getDataURI(data, callback) {
    if (window.Blob && data.blob instanceof window.Blob) {
        if (!isCriOS && URL && URL.createObjectURL) {
            callback(URL.createObjectURL(data.blob));
        } else if (window.FileReader) {
            var reader = new FileReader();
            reader.onload = function (e) {
                callback(reader.result);
            }
            reader.readAsDataURL(data.blob);
        }
        return;
    }
    callback('data:' + data.type + ';base64,' + btoa(data.bytes));
}

function createIframeFromData(data, domInsertCallback) {
    var iframe = document.createElement('iframe');
    if (!iframeDataUri) {
        iframe.src = 'about:blank';
        domInsertCallback(iframe);

        var fn = function (contents) {
            iframe.contentWindow.contents = contents;
            iframe.src = 'javascript:window["contents"]';
            
            iframe.setAttribute('sandbox', "allow-scripts");
        }

        if (requestBlob && data instanceof Blob) // blob
        {
            var reader = new FileReader();

            reader.onload = function (e) {
                fn(reader.result);
            }

            if (reader.readAsBinaryString)
                reader.readAsBinaryString(data);
            else
                reader.readAsText(data);
        } else {
            fn(data.bytes);
        }

    } else {
        getDataURI(data, function (dataUri) {
            iframe.setAttribute('sandbox', "allow-scripts");
            iframe.src = dataUri;
            domInsertCallback(iframe);
        });
    }
}

function createImageFromData(data, domInsertCallback) {
    var image = new Image();
    getDataURI(data, function (dataUri) {   
        if (data.originalUrl && dataUri.length > 32000) {
            image.onerror = function () {
                image = new Image();
                image.src = data.originalUrl;
                domInsertCallback(image);
            }
            image.onload = function () {
                domInsertCallback(image);
            }
            image.src = dataUri;
        } else {
            image.src = dataUri;
            domInsertCallback(image);
        }
    });
}

function getOrigin(a) {
    if (typeof a == "string") {
        var x = document.createElement('a');
        x.href = a;
        a = x;
    }
    return a.protocol + '//' + ((a.port !== '80' && a.port !== '443') ? a.host : a.hostname);
}

function fetchURL(url, options) {
    var options = options || {};

    var xhr = new XMLHttpRequest(), xdr;

    if (!supportBinaryFetch) {
        if (getOrigin(url) != getOrigin(window.location)) {
            xhr = new XDomainRequest();
            xdr = true;
            var orgUrl = url;
            var qPos = url.indexOf('?');
            url += qPos == -1 ? '?xdr' : (qPos == url.length - 1 ? 'xdr' : '&xdr');
            
            xhr.__parseHeaders = function(headers) {
                this.__responseHeaders = {};
                var headers = headers.split('\n');
                for(var i=0;i<headers.length;i++) {
                    var pos = headers[i].indexOf(':');
                    this.__responseHeaders[headers[i].substring(0, pos)] = headers[i].substr(pos+1);
                }
            }
            xhr.getResponseHeader = function(header)
            {
                return this.__responseHeaders[header];
            }
        }
    }

    if (!xdr) {
        try {
            xhr.withCredentials = true;
        } catch (e) {
        }
    }

    if (options.binary && requestBlob) {
        try {
            xhr.responseType = 'blob';
        } catch (e) {
            requestBlob = false;
        }
        if (xhr.responseType != 'blob') {
            requestBlob = false;
        }
    }
    if (options.binary && !requestBlob) {
        xhr.overrideMimeType && xhr.overrideMimeType('text/plain; charset=x-user-defined');
    }
    xhr.open(options.method || 'GET', url);

    fetchURL.timeout && (xhr.timeout = fetchURL.timeout);

    var ok, fail;
    xhr.ontimeout = function (event) {
        fail && fail();
    }

    xhr.then = function (onSuccess, onError) {
        ok = onSuccess;
        fail = onError;
    }

    if (xdr) {
        xhr.onerror = xhr.ontimeout;
        xhr.onload = function () {
            var data = {
                bytes : xhr.responseText,
                type : xhr.contentType
            };
            if (data.type.indexOf('text/base64') != -1) {
                data.type = data.type.split(',')[1];
                var headerEnd = data.bytes.indexOf('\n\n');
                if(headerEnd != -1) {
                    xhr.__parseHeaders(data.bytes.substring(0, headerEnd));
                    data.bytes = atob(data.bytes.substr(headerEnd+2));
                } else {
                    data.bytes = atob(data.bytes);
                }
                data.originalUrl = orgUrl;
                
            }
            if (options.json) {
                data = JSON.parse(data.bytes);
            }

            ok && ok(data, xhr);
        }
    } else {
        xhr.onreadystatechange = function () {
            if (xhr.readyState == 4) {
                var callback = (xhr.status >= 200 && xhr.status < 400) ? ok : fail
                if (callback) {
                    var data;
                    if (options.binary) {
                        try {
                            if (requestBlob) {
                                var reader = new FileReader();

                                reader.onload = function (e) {
                                    data = {
                                       bytes: reader.result,
                                       type: xhr.response.type,
                                       blob: xhr.response
                                    }
                                    callback && callback(data, xhr);
                                }

                                if (reader.readAsBinaryString)
                                    reader.readAsBinaryString(xhr.response);
                                else
                                    reader.readAsText(xhr.response);
                                return;
                            } else {
                                var arr;
                                try {
                                    arr = xhr.responseBody.toArray();
                                } catch (e) {
                                }
                                if (arr) {
                                    var i = 0, n = arr.length;
                                    var parts = [];
                                    while (i < n) {
                                        parts.push(String.fromCharCode.apply(String, arr.slice(i, i + 10000)));
                                        i += 10000;
                                    }
                                    data = {
                                        bytes : data = parts.join(''),
                                        type : xhr.getResponseHeader('Content-Type')
                                    }
                                } else {
                                    data = {
                                        bytes : options.binary ? xhr.responseText.replace(/.{1}/g, function (a) {
                                            return String.fromCharCode(a.charCodeAt(0) & 0xFF)
                                        }) : xhr.responseText,
                                        type : xhr.getResponseHeader('Content-Type')
                                    }
                                }
                            }
                        } catch (e) {
                            data = null
                        }
                    } else {
                        data = {
                            bytes : xhr.responseText,
                            type : xhr.getResponseHeader('Content-Type')
                        };
                    }
                    if (options.json) {
                        data = JSON.parse(data.bytes);
                    }
                    callback && callback(data, xhr);
                }
            }
        };
    }

    setTimeout(function () { // to be sure that then is handled before
                                // request
        // completes (from cache)
        if (options.post) {
            xhr.setRequestHeader && xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
            xhr.send(options.post);
        } else {
            xhr.send();
        }
    }, 0);

    return xhr;
}
