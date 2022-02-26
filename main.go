package main

import (
	"context"
	"encoding/json"
	"fmt"
	"log"
	"net/http"
	"os"
	"strings"

	"github.com/PuerkitoBio/goquery"
	"github.com/google/go-tika/tika"
	"github.com/mecenat/solr"
)

type Material struct {
	ID         string   `json:"id"`
	Filename   string   `json:"filename"`
	Sheetname  string   `json:"sheetname"`
	Mediatype  string   `json:"mediatype"`
	Cellvalues []string `json:"cellvalues"`
}

func (f *Material) ToMap() (doc map[string]interface{}, err error) {
	materialBytes, err := json.Marshal(f)
	if err != nil {
		return
	}
	err = json.Unmarshal(materialBytes, &doc)
	return
}

func main() {

	fmt.Println("Main function started")

	filename := "/mnt/c/Users/tmpz8/Documents/kassis/examples/fushin_qa.xlsx"
	tikaserveruri := "http://localhost:9998"
	solrserveruri := "http://localhost:8983"
	solrcorename := "kassiscore"

	//Get the file and open it
	file, err := os.Open(filename)
	if err != nil {
		fmt.Println(err)
	}

	//Close the file
	defer file.Close()

	ctx := context.Background()
	conn, err := solr.NewConnection(solrserveruri, solrcorename, http.DefaultClient)
	if err != nil {
		log.Fatal(err)
	}

	slr, err := solr.NewSingleClient(conn)
	if err != nil {
		log.Fatal(err)
	}

	//Create connection with tika server
	client := tika.NewClient(nil, tikaserveruri)

	//Read the content from file
	body, err := client.Parse(context.Background(), file)
	if err != nil {
		log.Fatal(err)
	}
	// Load the HTML document
	doc, err := goquery.NewDocumentFromReader(strings.NewReader(body))
	if err != nil {
		log.Fatal(err)
	}

	// Find the review items
	doc.Find("table tbody tr").Each(func(rindex int, rowselection *goquery.Selection) {
		// For each item found
		cells := []string{}

		innerselection := rowselection.Find("td")
		innerselection.Each(func(cindex int, cellsel *goquery.Selection) {
			fmt.Printf("cell %d %d: %s\n", rindex, cindex, cellsel.Text())

			cells = append(cells, cellsel.Text())
		})

		mediatype := "File:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
		id := fmt.Sprintf("%s%d", filename, rindex)
		doc := Material{ID: id, Mediatype: mediatype, Filename: filename, Sheetname: "1", Cellvalues: cells}

		fmt.Printf("%+v\n", doc)

		fmt.Println("try create to solr")

		res, err := slr.Create(ctx, &doc, &solr.WriteOptions{Commit: true})
		if err != nil {
			log.Fatal(err)
		}
		fmt.Println(res.Header)
		fmt.Println("---")
	})
}
