/*
 * Copyright (c) 2020, Nosto Solutions Ltd
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 * this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright notice,
 * this list of conditions and the following disclaimer in the documentation
 * and/or other materials provided with the distribution.
 *
 * 3. Neither the name of the copyright holder nor the names of its contributors
 * may be used to endorse or promote products derived from this software without
 * specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
 * ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * @author Nosto Solutions Ltd <contact@nosto.com>
 * @copyright 2020 Nosto Solutions Ltd
 * @license http://opensource.org/licenses/BSD-3-Clause BSD 3-Clause
 *
 */


/**
 * @typedef {Object} CMPConfig
 * @type Object
 * @property {String} merchant
 * @property {String} categoryCookie
 * @property {String} segmentCookie
 * @property {String} baseUrl
*/

define(['jquery'], function ($) {

	/**
	 *
	 * @param {string} merchant
	 * @param {string} cid
	 * @param {string} categoryCookie
	 * @param {string} segmentCookie
	 * @param {string} domain
	 */
	function getMapping(merchant, cid, categoryCookie, segmentCookie, domain) {
		const url = getRequestUrl(domain, merchant, cid);
		$.get(url)
			.done(function (data) {
				handleData(data, categoryCookie, segmentCookie);
			})
			.fail(function (error) {
				console.warn("Something went wrong trying to fetch segment mapping. Error code: "
					+ error.status)
			})
	}

	/**
	 * @param {object} data
	 * @param {string} categoryCookie
	 * @param {string} segmentCookie
	 */
	function handleData(data, categoryCookie, segmentCookie) {
		const segmentMapping = [];
		const categoryMapping = {};

		for (const property in data) {
			if (data[property]) {
				pushInUniqueValue(segmentMapping, data[property])
				categoryMapping[property] = segmentMapping.indexOf(data[property]);
			}
		}

		const categoryMapValue = JSON.stringify(categoryMapping);
		createCookie(categoryMapValue, categoryCookie)

		const segmentMappingJSON = JSON.stringify(segmentMapping);
		createCookie(segmentMappingJSON, segmentCookie)
	}

	/**
	 *
	 * @param {array} arr
	 * @param {string} item
	 */
	function pushInUniqueValue(arr, item) {
		if (arr.indexOf(item) === -1) {
			arr.push(item);
		}
	}

	/**
	 *
	 * @param {string} domain
	 * @param {string} merchant
	 * @param {string} cid
	 * @returns {string}
	 */
	function getRequestUrl(domain, merchant, cid) {
		return domain + "/cmp-mapping/magento?m=" + merchant + "&cid=" + cid;
	}

	/**
	 * @param {string} data
	 * @param {string} cookieName
	 */
	function createCookie(data, cookieName) {
		document.cookie = cookieName + "=" + data + "; expires=" + getExpireDate() + "; path=/";
	}

	/**
	 *
	 * @returns {string}
	 */
	function getExpireDate() {
		const date = new Date();
		const time = date.getTime();
		const expireTime = time + 1000 * 86400;
		date.setTime(expireTime);
		// noinspection JSUnresolvedFunction
		return date.toGMTString()
	}

	/**
	 *
	 * @param {string} cname
	 * @returns {string}
	 */
	function getCookie(cname) {
		const name = cname + "=";
		const decodedCookie = decodeURIComponent(document.cookie);
		const ca = decodedCookie.split(';');
		for (let i = 0; i < ca.length; i++) {
			let c = ca[i];
			while (c.charAt(0) === ' ') {
				c = c.substring(1);
			}
			if (c.indexOf(name) === 0) {
				return c.substring(name.length, c.length);
			}
		}
		return "";
	}

	// noinspection JSUnusedLocalSymbols
	/**
	 * @param {typeof CMPConfig} config
	 */
	function init(config) {
		const cid = getCookie("2c.cId");
		if (config.merchant === "") {
			console.warn("Nosto merchant id is missing. Segment mapping cannot be fetched")
			return;
		} else if (cid === "") {
			console.warn("Nosto cid is missing. Segment mapping cannot be fetched")
			return;
		} else if (config.categoryCookie === "") {
			console.warn("Nosto category mapping cookie name is missing. Segment mapping cannot be fetched")
		} else if (config.segmentCookie === "") {
			console.warn("Nosto segment mapping cookie name is missing. Segment mapping cannot be fetched")
		} else if (config.baseUrl === "") {
			console.warn("Nosto base url is missing. Segment mapping cannot be fetched")
		}

		getMapping(config.merchant,
			cid,
			config.categoryCookie,
			config.segmentCookie,
			config.baseUrl
		);

		return init;
	}

})
