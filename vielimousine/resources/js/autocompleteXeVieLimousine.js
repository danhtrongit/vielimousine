const data = [];
const newAreas = [
  {
    "id": 29,
    "name": "Hồ Chí Minh",
    "children": [
      {
        "id": 29,
        "name": "Hồ Chí Minh",
        "name_filter": "Hồ Chí Minh",
        "name_nospace": "hcm hochiminh sg saigon",
        "district": "",
        "is_pickup_dropoff_point": false
      },
      {
        "id": 376,
        "name": "Quận 1",
        "name_filter": "Quận 1",
        "name_nospace": "hcm hochiminh sg saigon q 1 q1 quan1",
        "district": "",
        "is_pickup_dropoff_point": false
      },
      {
        "id": 28284,
        "name": "Sân bay Tân Sơn Nhất",
        "name_filter": "Sân bay Tân Sơn Nhất",
        "name_nospace": "hcm hochiminh sg saigon sb tsn sanbay tansonnhat sbtsn sanbaytansonnhat",
        "district": "",
        "is_pickup_dropoff_point": false
      },
    ]
  },
  {
    "id": 2,
    "name": "Vũng Tàu",
    "children": [
      {
        "id": 114266,
        "name": "Long Hải",
        "name_filter": "Long Hải",
        "name_nospace": "longhai lh",
        "district": "",
        "is_pickup_dropoff_point": false
      },
      {
        "id": 156837,
        "name": "Hồ Tràm",
        "name_filter": "Hồ Tràm",
        "name_nospace": "ht hotram vt vungtau",
        "district": "",
        "is_pickup_dropoff_point": false
      },
      {
        "id": 2,
        "name": "Vũng Tàu",
        "name_filter": "Vũng Tàu",
        "name_nospace": "vt vungtau baria br brvt bariavungtau",
        "district": "",
        "is_pickup_dropoff_point": false
      },
    ]
  },
];

function getPointIdFromURLPathname(pathname) {
  var result = {
    fromId: null,
    toId: null
  }
  var areaNameIds = {
    'ho-chi-minh': 29,
    'sai-gon': 29,
    'quan-1-ho-chi-minh': 376,
    'san-bay-tan-son-nhat-ho-chi-minh': 28284,
    'ba-ria-vung-tau': 2,
    'long-hai-ba-ria-vung-tau': 114266,
    'ho-tram-vung-tau': 156837
  }
  var reg = /ve-xe-khach-tu-(.*)-di-(.*)/g;
  var matchPattern = reg.exec(pathname);
  if(matchPattern && matchPattern.length >= 3) {
    var fromName = matchPattern[1];
    var toName = matchPattern[2];
    result.fromId = areaNameIds[fromName];
    result.toId = areaNameIds[toName];
  }
  return result
}
function getFromToName(id) {
  var tempAreas = [];
  newAreas.map((parentArea) => {
    parentArea.children.map((area) => tempAreas.push(area));
  });
  var point = tempAreas.find((area) => area.id === id);
  return point ? point.name : ""
}

var url = new URL(window.location.href);
var fromToPoint = getPointIdFromURLPathname(url.pathname);
var fromId = fromToPoint.fromId;
var toId = fromToPoint.toId;
var date = "";
var dateFormat2 = "";
if (url.searchParams.get("departDate") != undefined || url.searchParams.get("departDate") != null) {
  let [d, m, y] = url.searchParams.get("departDate").split("-");
  date = `${d}-${m}-${y}`;
  dateFormat2 = `${y}-${m}-${d}`;
}
function createLunarCalendar(inp) {
  jQuery.each(inp, function (idx, elem) {
    var that = jQuery(elem);
    var data = that.parent().data();
    var tmpLunar = convertSolar2Lunar(parseInt(that.text()), data.month + 1, data.year, 7);
    var lunar = {
      lunarDay: tmpLunar[0],
      lunarMonth: tmpLunar[1],
      lunarYear: tmpLunar[2]
    }
    if (that.parent().find(".lunar").length == 0) {
      var $lunarSpan = '';
      if (idx == 0) {
        $lunarSpan = '<span class="lunar">' + lunar.lunarDay + '/' + lunar.lunarMonth + '</span>';
      } else {
        $lunarSpan = '<span class="lunar">' + lunar.lunarDay + '</span>';
      }
      that.parent().append($lunarSpan);
    }
    // 1-1 Tet Nguyen Dan
    if (lunar.lunarMonth == 1) {
      if (lunar.lunarDay == 1 || lunar.lunarDay == 2 || lunar.lunarDay == 3)
        jQuery(this).parent().addClass("tet-holiday");
    }
    if (that.parent().find('.ui-state-highlight').length > 0 || that.parent().find('.ui-state-active').length > 0) {
      that.parent().css("color", "white");
    }
    if (that.parent().hasClass('ui-datepicker-today')) {
      that.parent().find('span.lunar').css('color', 'white');
    }
  });
}
jQuery(document).ready(function () {
  jQuery('#btn').on('click', function () {
    var fromId = jQuery('input[name=from]').val() || url.searchParams.get('from');
    var toId = jQuery('input[name=to]').val() || url.searchParams.get('to');
    var f = document.getElementById('from').value;
    var t = document.getElementById('to').value;
    fromId.value = f == url.searchParams.get('from') ? t : f;
    toId.value = t == url.searchParams.get('to') ? f : t;
    // console.log(fromId, toId);
    jQuery('#to').val(fromId);
    jQuery('#from').val(toId);
  });

  var departDateElement = jQuery('input[name=departDate]');
  departDateElement.datepicker("destroy");
  departDateElement.datepicker({
    dateFormat: 'dd/mm/yy',
    monthNames: ["Tháng một", "Tháng hai", "Tháng ba", "Tháng tư", "Tháng năm", "Tháng sáu", "Tháng bảy", "Tháng tám", "Tháng chín", "Tháng mười", "Tháng mười một", "Tháng mười hai"],
    dayNamesMin: ["CN", "T2", "T3", "T4", "T5", "T6", "T7"],
    firstDay: 1,
    beforeShowDay: function (date) {
      var now = new Date();
      now.setHours(0);
      now.setMinutes(0);
      now.setSeconds(0);
      now.setMilliseconds(0);
      date.setHours(0);
      date.setMinutes(0);
      date.setSeconds(0);
      date.setMilliseconds(0);
      if (now > date) {
        return [false, "unavailable"];
      } else {
        return [true, "available"];
      }
    },
    beforeShow: function (input, inst) {
      setTimeout(function () {
        var $datepickerDiv = jQuery(inst.dpDiv[0]);
        if ($datepickerDiv.length > 0) {
          createLunarCalendar($datepickerDiv.find('td.available .ui-state-default'));
        }
      }, 10);
    },
    onChangeMonthYear: function (year, month, widget) {
      setTimeout(function () {
        var $date = jQuery('#ui-datepicker-div');
        if ($date.length > 0) {
          createLunarCalendar($date.find('td.available .ui-state-default'));
        }
      }, 10);
    },
    onClose: function () {
      jQuery(this).blur();
    }
  });
  departDateElement.datepicker('setDate', new Date(moment(dateFormat2).format('YYYY-MM-DD')));
});
function changeValue() {
  var url = new URL(window.location.href);
  var from = document.getElementById('inputFrom');
  var to = document.getElementById('inputTo');
  var froms = document.getElementById('inputFrom').value;
  var tos = document.getElementById('inputTo').value;
  from.value = froms === froms ? tos : froms;
  to.value = tos === tos ? froms : tos;
}

const P1 = 1
const P2 = 1000
const P3 = 2000
const P4 = 3000
const P5 = 4000
const P6 = 5000

function indexLevel1(nameOrigin, nameNormalize, txtOrigin, txtNormalize, nextCharCode) {
  const index1 = nameOrigin.indexOf(txtOrigin)
  const index2 = nameNormalize.indexOf(txtNormalize)

  if (index2 === 0) {
    if (index1 === 0) {
      return P1
    }
    return P2 + nextCharCode
  } else if (index2 > 0) {
    return P5 + nextCharCode
  }
  return P6
}

function indexLevel2(nameOrigin, nameNormalize, txtOrigin, txtNormalize, nextCharCode) {
  const index1 = nameOrigin.indexOf(txtOrigin)
  const index2 = nameNormalize.indexOf(txtNormalize)

  if (index2 === 0) {
    if (index1 === 0) {
      return P3
    }
    return P4 + nextCharCode
  } else if (index2 > 0) {
    return P5 + nextCharCode
  }
  return P6
}

function getNextCharCode(name, val) {
  const len = name.length
  const index = name.indexOf(val)
  const next = index + val.length

  if (next < len) {
    return next + name.charCodeAt(next)
  }
  return 0
}
function removeVietnameseSign(str) {
  str = str.replace(/à|á|ạ|ả|ã|â|ầ|ấ|ậ|ẩ|ẫ|ă|ằ|ắ|ặ|ẳ|ẵ/g, 'a');
  str = str.replace(/è|é|ẹ|ẻ|ẽ|ê|ề|ế|ệ|ể|ễ/g, 'e');
  str = str.replace(/ì|í|ị|ỉ|ĩ/g, 'i');
  str = str.replace(/ò|ó|ọ|ỏ|õ|ô|ồ|ố|ộ|ổ|ỗ|ơ|ờ|ớ|ợ|ở|ỡ/g, 'o');
  str = str.replace(/ù|ú|ụ|ủ|ũ|ư|ừ|ứ|ự|ử|ữ/g, 'u');
  str = str.replace(/ỳ|ý|ỵ|ỷ|ỹ/g, 'y');
  str = str.replace(/đ/g, 'd');
  str = str.replace(/À|Á|Ạ|Ả|Ã|Â|Ầ|Ấ|Ậ|Ẩ|Ẫ|Ă|Ằ|Ắ|Ặ|Ẳ|Ẵ/g, 'A');
  str = str.replace(/È|É|Ẹ|Ẻ|Ẽ|Ê|Ề|Ế|Ệ|Ể|Ễ/g, 'E');
  str = str.replace(/Ì|Í|Ị|Ỉ|Ĩ/g, 'I');
  str = str.replace(/Ò|Ó|Ọ|Ỏ|Õ|Ô|Ồ|Ố|Ộ|Ổ|Ỗ|Ơ|Ờ|Ớ|Ợ|Ở|Ỡ/g, 'O');
  str = str.replace(/Ù|Ú|Ụ|Ủ|Ũ|Ư|Ừ|Ứ|Ự|Ử|Ữ/g, 'U');
  str = str.replace(/Ỳ|Ý|Ỵ|Ỷ|Ỹ/g, 'Y');
  str = str.replace(/Đ/g, 'D');
  return str;
}
function normalizeUnicode(text) {
  let str = text
  if (str && str !== '') {
    str = str.trim()
    str = str.toLowerCase()
    str = str.replace(/à|á|ạ|ả|ã|â|ầ|ấ|ậ|ẩ|ẫ|ă|ằ|ắ|ặ|ẳ|ẵ/g, 'a');
    str = str.replace(/è|é|ẹ|ẻ|ẽ|ê|ề|ế|ệ|ể|ễ/g, 'e');
    str = str.replace(/ì|í|ị|ỉ|ĩ/g, 'i');
    str = str.replace(/ò|ó|ọ|ỏ|õ|ô|ồ|ố|ộ|ổ|ỗ|ơ|ờ|ớ|ợ|ở|ỡ/g, 'o');
    str = str.replace(/ù|ú|ụ|ủ|ũ|ư|ừ|ứ|ự|ử|ữ/g, 'u');
    str = str.replace(/ỳ|ý|ỵ|ỷ|ỹ/g, 'y');
    str = str.replace(/đ/g, 'd');
    str = str.replace(/À|Á|Ạ|Ả|Ã|Â|Ầ|Ấ|Ậ|Ẩ|Ẫ|Ă|Ằ|Ắ|Ặ|Ẳ|Ẵ/g, 'A');
    str = str.replace(/È|É|Ẹ|Ẻ|Ẽ|Ê|Ề|Ế|Ệ|Ể|Ễ/g, 'E');
    str = str.replace(/Ì|Í|Ị|Ỉ|Ĩ/g, 'I');
    str = str.replace(/Ò|Ó|Ọ|Ỏ|Õ|Ô|Ồ|Ố|Ộ|Ổ|Ỗ|Ơ|Ờ|Ớ|Ợ|Ở|Ỡ/g, 'O');
    str = str.replace(/Ù|Ú|Ụ|Ủ|Ũ|Ư|Ừ|Ứ|Ự|Ử|Ữ/g, 'U');
    str = str.replace(/Ỳ|Ý|Ỵ|Ỷ|Ỹ/g, 'Y');
    str = str.replace(/Đ/g, 'D');
    return str
  }
  return ''
}
function indexOf(obj, txtOrigin, txtNormalize) {
  const origin = obj.name
  const normalize = obj.name_filter
  const arr1 = origin.split(' ')
  const arr2 = normalize.split(' ')
  const nextCharCode = getNextCharCode(normalize, txtOrigin)
  const l = arr1.length
  let rs = P6

  for (let i = 0; i < l; i += 1) {
    const nameOrigin = arr1[i]
    const nameNormalize = arr2[i]

    if (i === 0) {
      rs = indexLevel1(nameOrigin, nameNormalize, txtOrigin, txtNormalize, nextCharCode)
    } else if (rs === P6) {
      rs = indexLevel2(nameOrigin, nameNormalize, txtOrigin, txtNormalize, nextCharCode)
    }
  }
  return rs
}
function compare(a, b) {
  if (a.index < b.index) {
    return -1
  }
  if (a.index > b.index) {
    return 1
  }
  return 0
}
function sortPlaces(arr, txtOrigin, txtNormalize) {
  const l = arr.length
  const res = []

  for (let i = 0; i < l; i += 1) {
    const obj = arr[i]
    const index = indexOf(obj, txtOrigin, txtNormalize)
    obj.index = index
    res[i] = obj
  }
  res.sort(compare)
  return res
}
function searchAreas(textSearch, Data) {
  const result = Data.filter((item) => {
    if (item.name.toUpperCase().indexOf(textSearch.toUpperCase()) !== -1) return true
    if (item.name_filter.toUpperCase().indexOf(textSearch.toUpperCase()) !== -1) return true
    // if (item.name.toUpperCase().indexOf(textSearch.toUpperCase()) !== -1) return true
    const list = item.name_nospace.split(' ').map(i => i.toUpperCase())
    // eslint-disable-next-line no-plusplus
    for (let i = 0; i <= list.length; i++) {
      if ((list[i] || '').toUpperCase().indexOf(textSearch.toUpperCase()) !== -1) return true
    }
    return false
  })
  // filter by category and get 5 row first
  let CITY = result.filter(item => item.category === 'CITY')
  CITY = sortPlaces(CITY, textSearch, normalizeUnicode(textSearch))
  let WARD = result.filter(item => item.category === 'WARD')
  WARD = sortPlaces(WARD, textSearch, normalizeUnicode(textSearch))
  let BUS_STATION = result.filter(item => item.category === 'BUS_STATION')
  BUS_STATION = sortPlaces(BUS_STATION, textSearch, normalizeUnicode(textSearch))
  let DISTRICT = result.filter(item => item.category === 'DISTRICT')
  DISTRICT = sortPlaces(DISTRICT, textSearch, normalizeUnicode(textSearch))
  let POPULAR = result.filter(item => item.category === 'POPULAR')
  POPULAR = sortPlaces(POPULAR, textSearch, normalizeUnicode(textSearch))
  let AIRPORT = result.filter(item => item.category === 'AIRPORT')
  AIRPORT = sortPlaces(AIRPORT, textSearch, normalizeUnicode(textSearch))
  return {
    CITY,
    WARD,
    BUS_STATION,
    DISTRICT,
    POPULAR,
    AIRPORT
  }
}
function filterAreas(data_source, keyword){
  const result = data_source.map((dataItem) => {
    let tmp_children = []
    dataItem.children.forEach((children) => {
      const children_keyword = children.name_nospace.split(' ').map(i => i.toUpperCase())
      // eslint-disable-next-line no-plusplus
      for (let i = 0; i <= children_keyword.length; i++) {
        if (tmp_children.indexOf(children) === -1 && (children_keyword[i] || '').toUpperCase().indexOf(keyword.toUpperCase()) !== -1) {
          tmp_children.push(children);
        }
      }
    });
    return {
      ...dataItem,
      children: tmp_children
    }
  });
  return result;
}
function click(elmnt) {
  console.log('elmnt', elmnt)
}
function generateListAreasHtml(data, target) {
  let targetType = 'from';
  let targetTypeName = 'nameFrom';
  let targetPointDistrict = 'pickupPointDistrict';
  let targetPointDistrictName = 'pickupPointName';
  if (target === 'to') {
    targetType = 'to';
    targetTypeName = 'nameTo';
    targetPointDistrict = 'dropoffPointDistrict';
    targetPointDistrictName = 'dropoffPointName';
  }
  let html = '';
  if (data && data.length > 0) {
    data.forEach((item, itemIdx) => {
      if (item.children && item.children.length > 0) {
        html += `<h4 style="padding:10px 16px 2px 16px;margin:0;color:#333333;font-weight:700;">${item.name}</h4>`;
        let children_html = '';
        item.children.forEach((children) => {
          if(children) {
            children_html += `
            <div name="${targetType}"
                onclick="document.getElementById('${targetType}').value = ${children.id};document.getElementById('${targetTypeName}').value = '${(children.name_filter)}';document.getElementById('${targetPointDistrict}').value = '${(children.is_pickup_dropoff_point ? children.district : '')}';document.getElementById('${targetPointDistrictName}').value = '${(children.is_pickup_dropoff_point ? children.name_filter : '')}';"
                style="padding:10px 30px;border:0;color:#4f4f4f;line-height:21px;"
                >
                ${children.name}
            </div>`;
          }
        });
        html += children_html;
        if (itemIdx != data.length - 1) {
          html += `<hr style="padding:0;margin:0 16px;color:#4f4f4f;"/>`;
        }
      }
    })
  }
  return html;
}
function findArea(targetId) {
  let result;
  newAreas.map((newArea) => {
    newArea.children.map((area) => {
      if (area.id === targetId) {
        result = area;
      }
    });
  });
  return result;
}
function autocompleteFrom(inp, arr) {
  let currentFocus;

  if (!url.searchParams.get('from') && !url.searchParams.get('to')) {
    document.getElementById('Info').style.display = 'none';
  } else {
    document.getElementById('Info').style.display = 'block';
  }
  if (url.searchParams.get('from')) {
    const newFromArea = findArea(+url.searchParams.get('from'));
    document.getElementById('inputFrom').value = newFromArea.name;
  }
  if (url.searchParams.get('to')) {
    const newToArea = findArea(+url.searchParams.get('to'));
    document.getElementById('inputTo').value = newToArea.name;
    document.getElementById('nameTo').innerHTML = newToArea.name;
  }

  // Show list items when focus
  inp.addEventListener('focus', function (e) {
    let i, b, c, d, w, buses;

    currentFocus = 0;
    a = document.createElement('DIV');
    a.setAttribute('id', this.id + 'autocomplete-list');
    a.setAttribute('class', 'autocomplete-items-from');
    a.style.width = '100%';
    a.style.overflowY = 'auto';
    a.style.maxHeight = '60vh';
    this.parentNode.appendChild(a);
    let cities = []
    let districts = []
    let bus = []
    let ward = []
    const result = searchAreas(document.getElementById('nameFrom').value, arr);
    cities = result.CITY
    districts = result.DISTRICT
    bus = result.BUS_STATION
    ward = result.WARD
    popular = result.POPULAR
    airport = result.AIRPORT
    b = document.createElement('DIV');
    c = document.createElement('DIV')
    d = document.createElement('DIV')
    w = document.createElement('DIV')
    buses = document.createElement('DIV')
    if (cities.length > 0) {
      for (i = 0; i < cities.length; i++) {
        c += ` <div name="from"
            onclick="document.getElementById('from').value = ${cities[i].id}; document.getElementById('nameFrom').value = '${(cities[i].name)}'">
            ${(cities[i].name)}
        </div>`;
      }
      c = c.replace("[object HTMLDivElement]", "")
    } else {
      c = ""
    }
    if (districts.length > 0) {
      for (i = 0; i < districts.length; i++) {
        d += `<div name="from"
            onclick="document.getElementById('from').value = ${districts[i].id}; document.getElementById('nameFrom').value = '${(districts[i].name)}'">
            ${(districts[i].name)}
        </div>`;
      }
      d = d.replace("[object HTMLDivElement]", "")
    } else {
      d = ""
    }
    if (bus.length > 0) {
      for (i = 0; i < bus.length; i++) {
        buses += `  <div name="from"
            onclick="document.getElementById('from').value = ${bus[i].id}; document.getElementById('nameFrom').value = '${(bus[i].name)}'">
            ${(bus[i].name)}
        </div>`;
      }
      buses = buses.replace("[object HTMLDivElement]", "")
    } else {
      buses = ""
    }
    if (ward.length > 0) {
      for (i = 0; i < ward.length; i++) {
        w += `<div name="from"
            onclick="document.getElementById('from').value = ${ward[i].id}; document.getElementById('nameFrom').value = '${(ward[i].name)}'">
            ${(ward[i].name)}
        </div>`;
      }
      w = w.replace("[object HTMLDivElement]", "")
    } else {
      w = ""
    }
    const new_result = filterAreas(newAreas, '');
    let new_html = generateListAreasHtml(new_result, 'from');
    if (new_html !== '') {
      b.innerHTML = new_html;
    } else {
      b.innerHTML = `
      ${cities.length > 0 ? '<h4 class="b ph2 "> Tỉnh - Thành Phố </h4>' : ''}${c}
      ${districts.length > 0 ? '<h4 class="b ph2 "> Quận - Huyện </h4>' : ''}${d}
      ${ward.length > 0 ? '<h4 class="b ph2 "> Wards </h4>' : ''}${w}
      ${bus.length > 0 ? '<h4 class="b ph2 "> Bus </h4>' : ''}${buses}
      `;
    }
    b.addEventListener('click', function (e) {
      inp.value = document.getElementById('nameFrom').value;
      closeAllLists();
    });
    a.appendChild(b);
    if (inp.value) {
      let x = document.getElementById(a.id);
      if (x) {
        x = x.getElementsByTagName('div');
        for (let i = 0; i < x.length; i++) {
          if (x[i].innerText == inp.value) {
            x[i].classList.add('autocomplete-active');
            a.scrollTop = x[i].offsetTop;
            currentFocus = i;
          }
        }
      }
    }
  });
  //Suggest when typing
  inp.addEventListener('input', function (e) {
    var a, b, c, d, w, buses, i, val = this.value;

    closeAllLists();
    currentFocus = 0;
    a = document.createElement('DIV');
    a.setAttribute('id', this.id + 'autocomplete-list');
    a.setAttribute('class', 'autocomplete-items-from');
    a.style.width = '100%';
    a.style.overflowY = 'auto';
    a.style.maxHeight = '60vh';
    this.parentNode.appendChild(a);
    let cities = []
    let districts = []
    let bus = []
    let ward = []
    const result = searchAreas(val, arr)
    cities = result.CITY
    districts = result.DISTRICT
    bus = result.BUS_STATION
    ward = result.WARD
    popular = result.POPULAR
    airport = result.AIRPORT
    b = document.createElement('DIV');
    c = document.createElement('DIV')
    d = document.createElement('DIV')
    w = document.createElement('DIV')
    buses = document.createElement('DIV')
    if (cities.length > 0) {
      for (i = 0; i < cities.length; i++) {
        c += ` <div name="from"
            onclick="document.getElementById('from').value = ${cities[i].id}; document.getElementById('nameFrom').value = '${(cities[i].name)}'">
            ${(cities[i].name)}
        </div>`;
      }
      c = c.replace("[object HTMLDivElement]", "")
    } else {
      c = ""
    }
    if (districts.length > 0) {
      for (i = 0; i < districts.length; i++) {
        d += `<div name="from"
            onclick="document.getElementById('from').value = ${districts[i].id}; document.getElementById('nameFrom').value = '${(districts[i].name)}'">
            ${(districts[i].name)}
        </div>`;
      }
      d = d.replace("[object HTMLDivElement]", "")
    } else {
      d = ""
    }
    if (bus.length > 0) {
      for (i = 0; i < bus.length; i++) {
        buses += `  <div name="from"
            onclick="document.getElementById('from').value = ${bus[i].id}; document.getElementById('nameFrom').value = '${(bus[i].name)}'">
            ${(bus[i].name)}
        </div>`;
      }
      buses = buses.replace("[object HTMLDivElement]", "")
    } else {
      buses = ""
    }
    if (ward.length > 0) {
      for (i = 0; i < ward.length; i++) {
        w += `<div name="from"
            onclick="document.getElementById('from').value = ${ward[i].id}; document.getElementById('nameFrom').value = '${(ward[i].name)}'">
            ${(ward[i].name)}
        </div>`;
      }
      w = w.replace("[object HTMLDivElement]", "")
    } else {
      w = ""
    }
    const new_result = filterAreas(newAreas, val);
    let new_html = generateListAreasHtml(new_result, 'from');
    if (new_html !== '') {
      b.innerHTML = new_html;
    } else {
      b.innerHTML = `
      ${cities.length > 0 ? '<h4 class="b ph2 "> Tỉnh - Thành Phố </h4>' : ''}${c}
      ${districts.length > 0 ? '<h4 class="b ph2 "> Quận - Huyện </h4>' : ''}${d}
      ${ward.length > 0 ? '<h4 class="b ph2 "> Phường - Xã </h4>' : ''}${w}
      ${bus.length > 0 ? '<h4 class="b ph2 "> Bến xe </h4>' : ''}${buses}
      `;
    }
    b.addEventListener('click', function (e) {
      inp.value = document.getElementById('nameFrom').value;
      closeAllLists();
    });
    a.appendChild(b);
    if (inp.value) {
      let x = document.getElementById(a.id);
      if (x) {
        x = x.getElementsByTagName('div');
        for (let i = 0; i < x.length; i++) {
          if (x[i].innerText == inp.value) {
            x[i].classList.add('autocomplete-active');
            a.scrollTop = x[i].offsetTop;
            currentFocus = i;
          }
        }
      }
    }
  });
  inp.addEventListener('keydown', function (e) {
    var x = document.getElementById(this.id + 'autocomplete-list');
    if (x) x = x.getElementsByTagName('div');
    if (e.keyCode == 40) {
      currentFocus++;
      addActive(x);
    } else if (e.keyCode == 38) {
      currentFocus--;
      addActive(x);
    } else if (e.keyCode == 13) {
      e.preventDefault();
      x[1].click()
      if (currentFocus > -1) {
        if (x) x[currentFocus].click();
      }
    } else if (e.keyCode == 9) {
      e.preventDefault();
      x[1].click()
      if (currentFocus > -1) {
        if (x) x[currentFocus].click();
      }
    }
  });

  function addActive(x) {
    /*a function to classify an item as "active":*/
    if (!x) return false;
    /*start by removing the "active" class on all items:*/
    removeActive(x);
    if (currentFocus >= x.length) currentFocus = 0;
    if (currentFocus < 0) currentFocus = x.length - 1;
    /*add class "autocomplete-active":*/
    x[currentFocus].classList.add('autocomplete-active');
    x[currentFocus].scrollIntoView(false);
  }

  function removeActive(x) {
    /*a function to remove the "active" class from all autocomplete items:*/
    for (var i = 0; i < x.length; i++) {
      x[i].classList.remove('autocomplete-active');
    }
  }

  function closeAllLists(elmnt) {
    var x = document.getElementsByClassName('autocomplete-items-from');
    for (var i = 0; i < x.length; i++) {
      if (elmnt != x[i] && elmnt != inp) {
        x[i].parentNode.removeChild(x[i]);
      }
    }
  }

  document.addEventListener('click', function (e) {
    closeAllLists(e.target);
  });
}
function autocompleteTo(inp, arr, cities) {
  let currentFocus;

  // Show list items when focus
  inp.addEventListener('focus', function (e) {
    let i, b, c, d, w, buses;

    currentFocus = 0;
    a = document.createElement('DIV');
    a.setAttribute('id', this.id + 'autocomplete-list');
    a.setAttribute('class', 'autocomplete-items-to');
    a.style.width = '100%';
    a.style.overflowY = 'auto';
    a.style.maxHeight = '60vh';
    this.parentNode.appendChild(a);
    let cities = []
    let districts = []
    let bus = []
    let ward = []
    const result = searchAreas(document.getElementById('nameTo').value, arr)
    cities = result.CITY
    districts = result.DISTRICT
    bus = result.BUS_STATION
    ward = result.WARD
    popular = result.POPULAR
    airport = result.AIRPORT
    b = document.createElement('DIV');
    c = document.createElement('DIV')
    d = document.createElement('DIV')
    w = document.createElement('DIV')
    buses = document.createElement('DIV')
    if (cities.length > 0) {
      for (i = 0; i < cities.length; i++) {
        c += ` <div name="to"
            onclick="document.getElementById('to').value = ${cities[i].id}; document.getElementById('nameTo').value = '${(cities[i].name)}'">
            ${(cities[i].name)}
        </div>`;
      }
      c = c.replace("[object HTMLDivElement]", "")
    } else {
      c = ""
    }
    if (districts.length > 0) {
      for (i = 0; i < districts.length; i++) {
        d += `<div name="to"
            onclick="document.getElementById('to').value = ${districts[i].id}; document.getElementById('nameTo').value = '${(districts[i].name)}'">
            ${(districts[i].name)}
        </div>`;
      }
      d = d.replace("[object HTMLDivElement]", "")
    } else {
      d = ""
    }
    if (bus.length > 0) {
      for (i = 0; i < bus.length; i++) {
        buses += `  <div name="to"
            onclick="document.getElementById('to').value = ${bus[i].id}; document.getElementById('nameTo').value = '${(bus[i].name)}'">
            ${(bus[i].name)}
        </div>`;
      }
      buses = buses.replace("[object HTMLDivElement]", "")
    } else {
      buses = ""
    }
    if (ward.length > 0) {
      for (i = 0; i < ward.length; i++) {
        w += `<div name="to"
            onclick="document.getElementById('to').value = ${ward[i].id}; document.getElementById('nameTo').value = '${(ward[i].name)}'">
            ${(ward[i].name)}
        </div>`;
      }
      w = w.replace("[object HTMLDivElement]", "")
    } else {
      w = ""
    }
    const new_result = filterAreas(newAreas, '');
    let new_html = generateListAreasHtml(new_result, 'to');
    if (new_html !== '') {
      b.innerHTML = new_html;
    } else {
      b.innerHTML = `
      ${cities.length > 0 ? '<h4 class="b ph2 "> Tỉnh - Thành Phố </h4>' : ''}${c}
      ${districts.length > 0 ? '<h4 class="b ph2 "> Quận - Huyện </h4>' : ''}${d}
      ${ward.length > 0 ? '<h4 class="b ph2 "> Phường - Xã </h4>' : ''}${w}
      ${bus.length > 0 ? '<h4 class="b ph2 "> Bến Xe </h4>' : ''}${buses}
      `;
    }
    b.addEventListener('click', function (e) {
      inp.value = document.getElementById('nameTo').value;
      if (document.getElementById('to').value) {
        document.getElementById('datepicker').focus();
      }
      closeAllLists();
    });
    a.appendChild(b);
    if (inp.value) {
      let x = document.getElementById(a.id);
      if (x) {
        x = x.getElementsByTagName('div');
        for (let i = 0; i < x.length; i++) {
          if (x[i].innerText == inp.value) {
            x[i].classList.add('autocomplete-active');
            a.scrollTop = x[i].offsetTop;
            currentFocus = i;
          }
        }
      }
    }
  });
  //Suggest when typing
  inp.addEventListener('input', function (e) {
    var a, b, c, d, w, buses, i, val = this.value;

    closeAllLists();
    currentFocus = 0;
    a = document.createElement('DIV');
    a.setAttribute('id', this.id + 'autocomplete-list');
    a.setAttribute('class', 'autocomplete-items-to');
    a.style.width = '100%';
    a.style.overflowY = 'auto';
    a.style.maxHeight = '60vh';
    this.parentNode.appendChild(a);
    let cities = []
    let districts = []
    let bus = []
    let ward = []
    const result = searchAreas(val, arr)
    cities = result.CITY
    districts = result.DISTRICT
    bus = result.BUS_STATION
    ward = result.WARD
    popular = result.POPULAR
    airport = result.AIRPORT
    b = document.createElement('DIV');
    c = document.createElement('DIV')
    d = document.createElement('DIV')
    w = document.createElement('DIV')
    buses = document.createElement('DIV')
    if (cities.length > 0) {
      for (i = 0; i < cities.length; i++) {
        c += ` <div name="to"
            onclick="document.getElementById('to').value = ${cities[i].id}; document.getElementById('nameTo').value = '${(cities[i].name)}'">
            ${(cities[i].name)}
        </div>`;
      }
      c = c.replace("[object HTMLDivElement]", "")
    } else {
      c = ""
    }
    if (districts.length > 0) {
      for (i = 0; i < districts.length; i++) {
        d += `<div name="to"
            onclick="document.getElementById('to').value = ${districts[i].id}; document.getElementById('nameTo').value = '${(districts[i].name)}'">
            ${(districts[i].name)}
        </div>`;
      }
      d = d.replace("[object HTMLDivElement]", "")
    } else {
      d = ""
    }
    if (bus.length > 0) {
      for (i = 0; i < bus.length; i++) {
        buses += `  <div name="to"
            onclick="document.getElementById('to').value = ${bus[i].id}; document.getElementById('nameTo').value = '${(bus[i].name)}'">
            ${(bus[i].name)}
        </div>`;
      }
      buses = buses.replace("[object HTMLDivElement]", "")
    } else {
      buses = ""
    }
    if (ward.length > 0) {
      for (i = 0; i < ward.length; i++) {
        w += `<div name="to"
            onclick="document.getElementById('to').value = ${ward[i].id}; document.getElementById('nameTo').value = '${(ward[i].name)}'">
            ${(ward[i].name)}
        </div>`;
      }
      w = w.replace("[object HTMLDivElement]", "")
    } else {
      w = ""
    }
    const new_result = filterAreas(newAreas, val);
    let new_html = generateListAreasHtml(new_result, 'to');
    if (new_html !== '') {
      b.innerHTML = new_html;
    } else {
      b.innerHTML = `
      ${cities.length > 0 ? '<h4 class="b ph2 "> Tỉnh - Thành Phố </h4>' : ''}${c}
      ${districts.length > 0 ? '<h4 class="b ph2 "> Quận - Huyện </h4>' : ''}${d}
      ${ward.length > 0 ? '<h4 class="b ph2 "> Phường - Xã </h4>' : ''}${w}
      ${bus.length > 0 ? '<h4 class="b ph2 "> Bến Xe </h4>' : ''}${buses}
      `;
    }
    b.addEventListener('click', function (e) {
      inp.value = document.getElementById('nameTo').value;
      if (document.getElementById('to').value) {
        document.getElementById('datepicker').focus();
      }
      closeAllLists();
    });
    a.appendChild(b);
    if (inp.value) {
      let x = document.getElementById(a.id);
      if (x) {
        x = x.getElementsByTagName('div');
        for (let i = 0; i < x.length; i++) {
          if (x[i].innerText == inp.value) {
            x[i].classList.add('autocomplete-active');
            a.scrollTop = x[i].offsetTop;
            currentFocus = i;
          }
        }
      }
    }
  });

  inp.addEventListener('keydown', function (e) {
    var x = document.getElementById(this.id + 'autocomplete-list');
    if (x) x = x.getElementsByTagName('div');
    if (e.keyCode == 40) {
      /*If the arrow DOWN key is pressed,
          increase the currentFocus variable:*/
      currentFocus++;
      /*and and make the current item more visible:*/
      addActive(x);
    } else if (e.keyCode == 38) {
      //up
      /*If the arrow UP key is pressed,
          decrease the currentFocus variable:*/
      currentFocus--;
      /*and and make the current item more visible:*/
      addActive(x);
    } else if (e.keyCode == 13) {
      /*If the ENTER key is pressed, prevent the form from being submitted,*/
      e.preventDefault();
      x[1].click()
      if (currentFocus > -1) {
        /*and simulate a click on the "active" item:*/
        if (x) x[currentFocus].click();
      }
    } else if (e.keyCode == 9) {

      console.log('currentFocus', currentFocus)
      console.log('x[1', x[1])
      console.log('x', x)
      e.preventDefault();
      x[1].click()
      /*If the TAB key is pressed*/
      if (currentFocus > -1) {
        /*and simulate a click on the "active" item:*/
        if (x) x[currentFocus].click();
      }
    }
  });

  function addActive(x) {
    /*a function to classify an item as "active":*/
    if (!x) return false;
    /*start by removing the "active" class on all items:*/
    removeActive(x);
    if (currentFocus >= x.length) currentFocus = 0;
    if (currentFocus < 0) currentFocus = x.length - 1;
    /*add class "autocomplete-active":*/
    x[currentFocus].classList.add('autocomplete-active');
    x[currentFocus].scrollIntoView(false);
  }

  function removeActive(x) {
    /*a function to remove the "active" class from all autocomplete items:*/
    for (var i = 0; i < x.length; i++) {
      x[i].classList.remove('autocomplete-active');
    }
  }

  function closeAllLists(elmnt) {
    var x = document.getElementsByClassName('autocomplete-items-to');
    for (var i = 0; i < x.length; i++) {
      if (elmnt != x[i] && elmnt != inp) {
        x[i].parentNode.removeChild(x[i]);
      }
    }
  }

  document.addEventListener('click', function (e) {
    closeAllLists(e.target);
  });
}

document.getElementById('inputTo').value = getFromToName(toId);
document.getElementById('inputFrom').value = getFromToName(fromId);
document.getElementById('to').value = toId;
document.getElementById('from').value = fromId;

/*initiate the autocomplete function on the "inputFrom" element, and pass along the countries array as possible autocomplete values:*/
autocompleteFrom(document.getElementById('inputFrom'), data);
autocompleteTo(document.getElementById('inputTo'), data);