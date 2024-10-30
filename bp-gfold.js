
var bp_gfold_ifTimeout, bp_gfold_l10n = false;

function bp_gfold_l(key) {
	if (bp_gfold_l10n === false) {
		var string = document.getElementById('bp-gfold-l10n').value;
		if (false && JSON && JSON.parse) {
			bp_gfold_l10n = JSON.parse(string);
		} else {
			bp_gfold_l10n = eval('('+string+')'); /* eeviil */
		}
	}
	return bp_gfold_l10n[key] || key;
}

function bp_gfold_onsubmit(e) {
	var submit = document.getElementById('gfold_submit');
	submit.setAttribute('disabled', 'disabled');
	var result = document.getElementById('gfold_result');
	result.className = 'progress';
	result.innerHTML = bp_gfold_l('uploading')+' ';
	var loader = document.createElement('img');
	loader.src = document.getElementById('bp-gfold-loader').value;
	result.appendChild(loader);
	result.style.display = 'inline-block';
	
	document.getElementById('gfold_uploader').onload = function() {
		bp_gfold_ifTimeout = setTimeout(function() {
			bp_gfold_result(false, false);
		}, 5000);
	};
}

function bp_gfold_result(message, state) {
	if (state !== undefined) {
		var submit = document.getElementById('gfold_submit');
		submit.removeAttribute('disabled');
	}
	var result = document.getElementById('gfold_result');
	result.className = state == true ? 'success' : (state === false ? 'error' : 'progress');
	result.innerHTML = message || bp_gfold_l('fail');
	result.style.display = 'inline-block';
}

function bp_gfold_upload_url(success, total) {
	var path = window.location.pathname;
	if (path.substr(-1) != '/') {path += '/';}
	
	var split = path.split('/'); split.length -= 1;
	if (split[split.length-1].substr(0, 6) == 'upload') {split.length -= 1;}
	split.push('upload-'+success+'-'+total);
	
	window.location.pathname = split.join('/');;
}

function bp_gfold_upload_event(data) {
	var success, total;
	clearTimeout(bp_gfold_ifTimeout);
	if (data.substr(0, 7) == 'result:') {
		success = data.substr(7).split('/');
		total = success[1]; success = success[0];
		if (success > 0) {
			bp_gfold_upload_url(success, total);
		} else {
			bp_gfold_result(false, false);
		}
	} else if (data == 'auth') {
		bp_gfold_result(bp_gfold_l('auth_fail'), false);
	} else {
		bp_gfold_result(false, false);
	}
}

function bp_gfold_typeless(filename) {
	var index = filename.lastIndexOf('.');
	/* consider up to five characters an extension */
	if (index < filename.length - 6) {return filename;}
	return filename.substr(0, index);
}

function bp_gfold_form() {
	var data = {};
	data.url = document.getElementById('gfold_form').action;
	data.nonce = document.getElementById('bp-gfold-nonce').value;
	data.groupId = document.getElementById('bp-gfold-group-id').value;
	data.groupHash = document.getElementById('bp-gfold-group-hash').value;
	return data;
}

function bp_gfold_ajax(data, success, error, complete) {
	
	if (!jQuery && !$) {
		setTimeout(error, 50);
		setTimeout(complete, 70);
		return false;
	}
	
	var form = bp_gfold_form();
	data.ajax = true;
	data['bp-gfold-nonce'] = form.nonce;
	data['bp-gfold-group-id'] = form.groupId;
	data['bp-gfold-group-hash'] = form.groupHash;
	
	(jQuery || $).ajax({
		url: form.url,
		type: 'POST',
		data: data,
		dataType: 'json',
		success: success,
		error: error,
		complete: complete
	});

	return true;
}

function bpgfoldrn(e) {
	var row = e.parentNode.parentNode.parentNode;
	if (row.getAttribute('data-rn') == 1) {return;}
	if (!jQuery && !$) {return;}

	var old = row.getElementsByClassName('title')[0];
	old = old.innerText || (jQuery || $)(old).text();
	var oldClean = bp_gfold_typeless(old);
	var name = prompt(bp_gfold_l('rename_dialog').replace('%s', old), oldClean);
	if (name === null || name == '' || name == oldClean || name == old || (name.trim && name.trim() == '')) {return;}
	
	var ajax = bp_gfold_ajax({
		'bp-gfold-action': 'rename',
		name: row.getAttribute('data-fn'),
		newName: name
	}, function(data) {
		if (data.success) {
			row.setAttribute('data-fn', data.info.full);
			row.getElementsByClassName('title')[0].innerHTML = data.info.disp;
			var span = row.getElementsByClassName('right')[0].getElementsByTagName('span')[0];
			span.childNodes[0].href = data.info.link;
			span.childNodes[1].href = data.info.link_download;
			return;
		}
		if (data.info == 'notfound') {
			alert(bp_gfold_l('rename_notfound'));
		} else {
			alert(bp_gfold_l('rename_fail'));
		}
	}, function() {
		/* error */
	}, function() {
		row.removeAttribute('data-rn');
		row.style.opacity = 1;
	});
	
	if (ajax) {
		row.setAttribute('data-rn', 1);
		row.style.opacity = 0.3;
	}

}

function bpgfoldd(e) {
	var row = e.parentNode.parentNode.parentNode;
	if (row.getAttribute('data-d') == 1) {return;}
	
	var name = row.getElementsByClassName('title')[0].innerHTML;
	if (confirm(bp_gfold_l('delete_dialog').replace('%s', name)) != true) {return;}

	var ajax = bp_gfold_ajax({
		'bp-gfold-action': 'delete',
		name: row.getAttribute('data-fn')
	}, function(data) {
		if (data.success) {
			var cHeight = row.clientHeight;
			row.style.opacity = 0;
			if (cHeight > 10) {
				var filler = document.createElement('div');
				filler.style.height = (cHeight-1)+'px';
				filler.className = 'filler';
				row.parentNode.replaceChild(filler, row);
				setTimeout(function() {
					filler.style.height = 0;
				}, 30);
			} else {
				setTimeout(function() {
					row.parentNode.removeChild(row);
				}, 200);
			}
			return;
		}
		alert(bp_gfold_l('delete_fail'));
	}, function(data) {
		/* error */
	}, function() {
		row.removeAttribute('data-d');
		row.style.opacity = 1;
	});
	
	if (ajax) {
		row.setAttribute('data-d', 1);
		row.style.opacity = 0.3;
	}

}
