function initDepartments() {
    var i = deptarray.length;
    deptarray[i++] = new dept("School of Computer Science and Technology", "School of Computer Science and Technology", "CM010");
    deptarray[i++] = new dept("School of Sport Science and Physical Activity", "School of Sport Science and Physical Activity", "BD032");
    deptarray.sort(NameOrder);
}

function initPOS() {
    var i = posarray.length;
    posarray[i++] = new pos("Computer Science - BSc (Hons) - Ltn - Y1 Oct FT", "Computer Science - BSc (Hons) - Ltn - Year 1 Oct FT", "BSCCS-S/10AA/1/FT", "CM010", "Undergraduate Year 1");
    posarray[i++] = new pos("Computer Science - BSc (Hons) - Ltn - Y2 Oct FT", "Computer Science - BSc (Hons) - Ltn - Year 2 Oct FT", "BSCCS-S/10AA/2/FT", "CM010", "Undergraduate Year 2");
    posarray[i++] = new pos("Computer Science - BSc (Hons) - Ltn - Y3 Oct FT", "Computer Science - BSc (Hons) - Ltn - Year 3 Oct FT", "BSCCS-S/10AA/3/FT", "CM010", "Undergraduate Year 3");
    posarray[i++] = new pos("Sport and Physical Education (BSc - With Professional Practice Year) - BSc (Hons) - Bed - Y3 Oct FT", "Sport and Physical Education (BSc - With Professional Practice Year) - BSc (Hons) - Bed - Year 3 Oct FT", "BSSESABF/10AB/3/FT", "BD032", "Undergraduate Year 3");
    posarray.sort(NameOrder);
}

function initPOSGroup() {
    var i = posgrouparray.length;
    posgrouparray[i++] = new posgroup("Undergraduate Year 4");
    posgrouparray[i++] = new posgroup("Postgraduate");
    posgrouparray[i++] = new posgroup("Undergraduate Other");
    posgrouparray[i++] = new posgroup("Undergraduate Year 1");
    posgrouparray[i++] = new posgroup("Undergraduate Year 2");
    posgrouparray[i++] = new posgroup("Undergraduate Year 3");
    posgrouparray[i++] = new posgroup("Foundation Year");
    posgrouparray[i++] = new posgroup("Apprenticeship");
    posgrouparray.sort(NameOrder);
}