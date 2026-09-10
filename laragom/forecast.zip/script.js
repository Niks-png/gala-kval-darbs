function switchlight() {

    const button = document.getElementById("javaButton");

    if(button.textContent == "Light"){
        document.body.style.filter = "invert(1) hue-rotate(180deg)";
        button.textContent ="Dark";
    }
    else if(button.textContent =="Dark"){
        document.body.style.filter="invert(0) hue-rotate(0deg)";
        button.textContent ="Light";
    }
}
