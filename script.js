function select_ns(form)
{
	ns = parseInt(form['ns_select'].value);
	chk = form['ns[]'];
	len = chk.length;
	for (i = 0; i < len; i++) chk[i].checked = false;
	if (isNaN(ns)) return;
	switch (ns)
	{
		case 1: // default
			indexes = new Array(
				0, 1,  2,  3,  4,  5,  6,  7,  8,
				9, 11, 12, 13, 14, 15, 16, 17);
			break;
		case 2: // all
			indexes = new Array(
				0, 1,  2,  3,  4,  5,  6,  7,  8,
				9, 10, 11, 12, 13, 14, 15, 16, 17);
			break;
		case 3: // article only
			indexes = new Array(0, 9);
			break;
		case 4: // without talk
			indexes = new Array(
				0, 1,  2,  3,  4,  5,  6,  7,  8);
			break;
		case 5: // talk only
			indexes = new Array(
				9, 10, 11, 12, 13, 14, 15, 16, 17);
			break;
	}
	len = indexes.length;
	for (i = 0; i < len; i++) chk[indexes[i]].checked = true;
}

