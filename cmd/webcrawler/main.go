package main

import (
	"bytes"
	"flag"
	"fmt"
	"github.com/PuerkitoBio/goquery"
	"github.com/nakamura-akifumi/kassis"
	"io"
	"io/ioutil"
	"net/http"
	"os"
)

var Cfg kassiscore.KENVCONF

func main() {
	fmt.Printf("kassis webcrawler. version %km,ns (Revison:%s)\n", kassiscore.VERSION, kassiscore.REVISION)
	fmt.Println("Main function started")

	flag.Parse()
	Cfg := kassiscore.LoadConfig()

	fmt.Println(Cfg.ExtDir)

	if len(flag.Args()) != 1 {
		fmt.Println("Error: no uri (example: https://wiki.example.com/operationguide")
		os.Exit(1)
	}

	// load config

	uri := flag.Arg(0)

	fmt.Print(uri)
	fmt.Print("\n")

	// Getリクエスト
	res, _ := http.Get(uri)
	defer res.Body.Close()

	// 読み取り
	buf, _ := ioutil.ReadAll(res.Body)

	// 文字コード判定
	//det := chardet.NewTextDetector()
	//detResult, _ := det.DetectBest(buf)

	// 文字コード変換
	bReader := bytes.NewReader(buf)
	//reader, _ := charset.NewReaderLabel(detResult.Charset, bReader)

	bytebuf := new(bytes.Buffer)
	io.Copy(bytebuf, bReader)
	ioutil.WriteFile("./test.txt", bytebuf.Bytes(), os.ModePerm)

	// HTMLパース
	//fmt.Println(bytebuf)
	doc, _ := goquery.NewDocumentFromReader(bytebuf)

	// titleを抜き出し
	rslt := doc.Find("title").Text()
	fmt.Printf("title:%s", rslt)

	doc.Find("a").Each(func(_ int, s *goquery.Selection) {
		url, _ := s.Attr("href")
		fmt.Println(url)
	})
}
