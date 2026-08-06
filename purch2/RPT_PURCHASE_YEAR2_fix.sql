USE [msdata]
GO

SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO

ALTER PROCEDURE [dbo].[RPT_PURCHASE_YEAR2]
    @START_DATE datetime,
    @END_DATE datetime,
    @CODE varchar(8),
    @SUP_CODE varchar(8),
    @RCV_NOMOR varchar(20)
AS
BEGIN
    SET NOCOUNT ON;

    -- Filter kosong = semua.
    SET @CODE = ISNULL(NULLIF(LTRIM(RTRIM(@CODE)), ''), '%');
    SET @SUP_CODE = ISNULL(NULLIF(LTRIM(RTRIM(@SUP_CODE)), ''), '%');
    SET @RCV_NOMOR = ISNULL(NULLIF(LTRIM(RTRIM(@RCV_NOMOR)), ''), '%');

    SELECT
        PD.PO_ID,
        PD.ITEM_ID,
        PD.QTY,
        PD.POD_PRICE,
        PD.POD_UNIT,
        PD.POD_DUE,
        P.PO_NUM,
        P.PO_DATE,
        P.PO_CUR,
        I.ITEM_CODE,
        I.ITEM_NAME,
        R.RCV_NO AS RCV_NOMOR,
        R.RCV_DATE,
        S.SUP_CODE,
        S.SUP_COMP,
        SUM(ISNULL(RD.RCVD_QTY, 0)) AS RCVD_QTY,
        RD.POD_PRICE AS RCV_PRICE,
        R.RCV_NOMOR AS REC
    FROM
    (
        SELECT
            PO_ID,
            ITEM_ID,
            MAX(POD_QTY) AS QTY,
            MAX(POD_PRICE) AS POD_PRICE,
            MAX(POD_UNIT) AS POD_UNIT,
            MAX(POD_DUE) AS POD_DUE
        FROM dbo.PO_DETAIL
        GROUP BY PO_ID, ITEM_ID
    ) AS PD
    INNER JOIN dbo.PO AS P
        ON PD.PO_ID = P.PO_ID
    INNER JOIN dbo.ITEMS AS I
        ON PD.ITEM_ID = I.ITEM_ID
    INNER JOIN dbo.SUPPLIER AS S
        ON P.SUP_ID = S.SUP_ID
    LEFT OUTER JOIN dbo.RECEIVE_DETAIL AS RD
        ON PD.PO_ID = RD.PO_ID
       AND PD.ITEM_ID = RD.ITEM_ID
    LEFT OUTER JOIN dbo.RECEIVE AS R
        ON RD.RCV_ID = R.RCV_ID
    WHERE
        P.PO_DATE BETWEEN @START_DATE AND @END_DATE
        AND I.ITEM_CODE LIKE @CODE
        AND S.SUP_CODE LIKE @SUP_CODE
        AND
        (
            @RCV_NOMOR = '%'
            OR ISNULL(R.RCV_NOMOR, '') LIKE @RCV_NOMOR
        )
    GROUP BY
        PD.PO_ID,
        PD.ITEM_ID,
        PD.QTY,
        PD.POD_PRICE,
        PD.POD_UNIT,
        PD.POD_DUE,
        P.PO_NUM,
        P.PO_DATE,
        P.PO_CUR,
        I.ITEM_CODE,
        I.ITEM_NAME,
        R.RCV_NO,
        R.RCV_DATE,
        S.SUP_CODE,
        S.SUP_COMP,
        RD.POD_PRICE,
        R.RCV_NOMOR
    ORDER BY
        P.PO_DATE,
        P.PO_NUM,
        I.ITEM_CODE,
        R.RCV_DATE,
        R.RCV_NO;
END
GO
