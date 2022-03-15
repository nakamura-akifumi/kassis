package main

import (
	"encoding/json"
	"fmt"
	"net/http"

	"github.com/labstack/echo/v4"
)

func getMaterials(c echo.Context) error {
	res, err := service.SolrQuery("http://localhost:8983", "kassiscore")
	if err != nil {
		fmt.Println("err")
	}

	var materials []*service.Material

	fBytes, err := res.Data.Docs.ToBytes()
	if err != nil {
		fmt.Println(err)
	}
	err = json.Unmarshal(fBytes, &materials)
	if err != nil {
		fmt.Println(err)
	}

	return c.String(http.StatusOK, materials)
}

func main() {
	e := echo.New()
	e.GET("/", func(c echo.Context) error {
		return c.String(http.StatusOK, "Hello, World!")
	})
	e.GET("/materials", getMaterials)

	e.Logger.Fatal(e.Start(":1323"))
}
