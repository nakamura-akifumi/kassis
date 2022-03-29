package kassiscore

import (
	"encoding/json"
	"fmt"
	"github.com/labstack/echo/v4"
	"net/http"
)

func HandlerGetMaterials(c echo.Context) error {
	var webresponse KWRIF
	webresponse.NumFound = 0
	webresponse.ResponseStatus = "success"

	q := c.QueryParam("q")
	res, err := SolrQuery("http://localhost:8983", "kassiscore", q)
	if err != nil {
		fmt.Println("err")
	}

	webresponse.NumFound = res.Data.NumFound

	fBytes, err := res.Data.Docs.ToBytes()
	if err != nil {
		fmt.Println(err)
	}
	err = json.Unmarshal(fBytes, &webresponse.Materials)
	if err != nil {
		fmt.Println(err)
	}

	return c.Render(http.StatusOK, "materials", webresponse)

}

func HandlerGetApiMaterials(c echo.Context) error {
	var webresponse KWRIF
	webresponse.NumFound = 0
	webresponse.ResponseStatus = "success"

	q := c.QueryParam("q")
	res, err := SolrQuery("http://localhost:8983", "kassiscore", q)
	if err != nil {
		fmt.Println("err")
	}

	webresponse.NumFound = res.Data.NumFound

	fBytes, err := res.Data.Docs.ToBytes()
	if err != nil {
		fmt.Println(err)
	}
	err = json.Unmarshal(fBytes, &webresponse.Materials)
	if err != nil {
		fmt.Println(err)
	}

	encodedJSON, err := json.Marshal(&webresponse)
	if err != nil {
		fmt.Println(err)
	}

	return c.JSONBlob(http.StatusOK, encodedJSON)
}
